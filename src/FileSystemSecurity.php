<?php
namespace nomelodic\fss;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FileSystemSecurity
{
    /**
     * Корневая папка проекта
     * @var string
     */
    private $baseDir;

    /**
     * Список исключений
     * @var string[]
     */
    private $exclude;

    /**
     * Список файлов для обхода
     * @var string[]
     */
    private $include;

    /**
     * Callback-функция, выполняемая после завершения проверки контрольной суммы
     * @var callable
     */
    private $callback;

    private $checksumFile = 'fs_checksum';

    /**
     * @param  string[] $config
     * @throws Exception
     */
    function __construct(array $config)
    {
        if (!isset($config['baseDir'])) $this->error('Не указана корневая директория.');

        $this->baseDir  = rtrim($config['baseDir'], '/') . '/';
        $this->callback = $config['callback'] ?? null;

        // TODO: Продумать более гибкий вариант масок
        $this->exclude  = array_merge($config['exclude'] ?? ['d|.git', 'd|.idea', 'd|.buildpath', 'd|.project', 'd|.settings'], ['f|fs_checksum']);
        $this->include  = array_merge($config['include'] ?? ['f|*.php', 'f|*.html', 'f|.env', 'f|.htaccess', 'f|*.sh', 'f|*.bat'], ['d|*']);
    }

    /**
     * Составление слепка и контрольной суммы
     *
     * @return void
     */
    public function scan()
    {
        $files = $this->getList();
        $json = json_encode($files);

        $file = [
            'checksum' => md5($json),
            'rules' => [
                'ex' => $this->exclude,
                'in' => $this->include
            ],
            'list' => $files
        ];

        file_put_contents($this->getChecksumPath(), json_encode($file), LOCK_EX);
    }

    /**
     * Проверка слепка и контрольной суммы
     *
     * @return callable|string[]
     * @throws Exception
     */
    public function check()
    {
        $files = $this->getList();
        $json = json_encode($files);

        $checksum = $this->getChecksum();
        $diff = [
            'created'  => [],
            'modified' => [],
            'deleted'  => []
        ];

        $status = true;

        // Если записанная контрольная не совпадает с текущей
        if ($checksum['checksum'] !== md5($json))
        {
            // Записываем в переменную список файлов
            $old = $checksum['list'];

            // Перебираем текущий список
            foreach ($files as $file => $info)
            {
                // Если файл есть в имеющемся списке
                if (isset($old[$file]))
                {
                    // Если время изменения лии вес не совпадают
                    if ((int) $old[$file]['modified'] !== (int) $info['modified'] || (int) $old[$file]['size'] !== (int) $info['size'])
                    {
                        // Записываем как модифицированный
                        $diff['modified'][$file] = [
                            'old' => $old[$file],
                            'new' => $info
                        ];

                        $status = false;
                    }

                    // Удаляем файл из списка
                    unset($old[$file]);
                }
                else
                {
                    // Иначе записываем как созданный
                    $diff['created'][$file] = [
                        'new' => $info
                    ];

                    $status = false;
                }
            }

            // Если в списке файлов остались записи
            if (!empty($old))
            {
                // Значит помечаем все как удаленные
                foreach ($old as $file => $info)
                {
                    $diff['deleted'][$file] = [
                        'old' => $info
                    ];
                }

                $status = false;
            }
        }

        $call = $this->callback;
        return $call ? $call($status, $diff) : compact('status', 'diff');
    }

    /**
     * Сканирование файловой системы
     *
     * @return string[][]
     */
    private function getList()
    {
        $dir      = new RecursiveDirectoryIterator($this->baseDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
        $filter   = (new Filter($dir))
            ->parseExclude($this->exclude)
            ->parseInclude($this->include);

        $iterator = new RecursiveIteratorIterator($filter);

        $files = [];

        foreach ($iterator as $info)
        {
            /* @var $info SplFileInfo */
            $files[str_replace($this->baseDir, '', $info->getPathname())] = [
                'modified' => $info->getMTime(),
                'size'     => $info->getSize()
            ];
        }

        return $files;
    }

    /**
     * @return string[][]
     * @throws Exception
     */
    private function getChecksum()
    {
        $path = $this->getChecksumPath();

        if (!file_exists($path)) $this->error('Не найден файл слепка. Пожалуйста, воспользуйтесь методом scan() для его создания.');

        return json_decode(file_get_contents($path), true);
    }

    /**
     * @return string
     */
    private function getChecksumPath()
    {
        return $this->baseDir . $this->checksumFile;
    }

    /**
     * @param  string $message
     * @throws Exception
     */
    private function error(string $message)
    {
        throw new Exception($message);
    }
}