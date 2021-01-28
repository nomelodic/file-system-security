<?php
namespace nomelodic\fss\assets;

use RecursiveFilterIterator;

class Filter extends RecursiveFilterIterator {

    /**
     * @var string[]
     */
    private $exclude = ['files' => '', 'dirs' => ''];

    /**
     * @var string[]
     */
    private $include = ['files' => '', 'dirs' => ''];

    /**
     * @return bool
     */
    public function accept()
    {
        $current = $this->current();

        /* @var $exclude bool */
        /* @var $include bool */
        foreach (['exclude', 'include'] as $type)
        {
            $list = $this->$type;
            $files_mask = $list['files'];
            $dirs_mask  = $list['dirs'];

            $$type = $list
                ? (!empty($files_mask) && preg_match($files_mask, $current->getFilename()) && $current->isFile()) || (!empty($dirs_mask) && preg_match($dirs_mask, $current->getFilename()) && $current->isDir())
                : $type === 'include';
        }

        return !$exclude && $include;
    }

    /**
     * @return Filter|RecursiveFilterIterator
     */
    public function getChildren()
    {
        return (new self($this->getInnerIterator()->getChildren()))
            ->setExclude($this->exclude)
            ->setInclude($this->include);
    }

    /**
     * @param  string[] $list
     * @return Filter
     */
    public function parseExclude(array $list)
    {
        return $this->setExclude($this->prepareList($list));
    }

    /**
     * @param  string[] $list
     * @return Filter
     */
    private function setExclude(array $list)
    {
        $this->exclude = $list;

        return $this;
    }

    /**
     * @param  string[] $list
     * @return Filter
     */
    public function parseInclude(array $list)
    {
        return $this->setInclude($this->prepareList($list));
    }

    /**
     * @param  string[] $list
     * @return Filter
     */
    private function setInclude(array $list)
    {
        $this->include = $list;

        return $this;
    }

    /**
     * @param  string[] $list
     * @return string[]
     */
    private function prepareList(array $list)
    {
        $return = ['f' => [], 'd' => []];

        foreach ($list as $item)
        {
            $e = explode('|', $item);
            $e[1] = str_replace('*', '(.*)', $e[1]);

            if (in_array($e[0], ['d', 'f'])) $return[$e[0]][] = $e[1];
        }

        $files = $return['f'] ? '/^(' . implode('|', $return['f']) . ')$/' : '';
        $dirs  = $return['d'] ? '/^(' . implode('|', $return['d']) . ')$/' : '';

        return compact('files', 'dirs');
    }
}