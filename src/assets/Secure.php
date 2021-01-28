<?php
namespace nomelodic\fss\assets;

/**
 * Класс для проверки файлов на наличие нежелательных вхождений
 * @package nomelodic\fss\assets
 */
class Secure {

    /**
     * Список нежелательных вхождений
     * Ключ - само вхождение, значение - число 1..5, обозначающее уровень опасности
     * @var string[]
     */
    private $warnings = [
        'exec',
        'chmod',
        'mkdir',
        'file_put_contents',
        'fwrite',
        '$GLOBAL',
        'base64_decode',
        'getenv',
        'set_time_limit',
        'rmdir',
        'mail',
        'curl_init',
        'header',
    ];

    /**
     * Количество символов по краям, захватываемое вместе с найденным вхождением
     * @var int
     */
    private $paddings = 30;

    /**
     * Проверка файла на наличие нежелательных вхождений
     *
     * @param  string $file Путь к проверяемому файлу
     * @return string[]
     */
    public function scan(string $file)
    {
        $return = [];

        if (file_exists($file))
        {
            $content = file_get_contents($file);

            foreach ($this->warnings as $key)
            {
                $finish = false;
                $search = preg_replace('/\t/us', ' ', $content);
                $search = preg_replace('/\n/us', ' ', $search);
                $search = preg_replace('/\s+/us', ' ', $search);

                do
                {
                    $pos = mb_strpos($search, $key);

                    if ($pos !== false)
                    {
                        $append = false;

                        if ($pos > 0)
                        {
                            $left_char = mb_substr($search, $pos - 1, 1);
                            $append = in_array($left_char, [' ', ';']);
                        }

                        if ($append)
                        {
                            $start = $pos - $this->paddings;
                            if ($start < 0) $start = 0;

                            $len = 2*$this->paddings + strlen($key);
                            $search_string = mb_substr($search, $start, $len);

                            $return[] = [
                                'key'    => $key,
                                'string' => ($start > 0 ? '...' : '') . $search_string . (mb_strlen($search_string) >= $len ? '...' : ''),
                                'offset' => $pos
                            ];
                        }

                        $search = mb_substr($search, $pos + strlen($key));
                    }
                    else
                        $finish = true;
                }
                while (!$finish);
            }
        }

        return $return;
    }

    /**
     * Установка отступов
     *
     * @param  int $paddings
     * @return void
     */
    public function setPaddings(int $paddings)
    {
        $this->paddings = $paddings;
    }
}