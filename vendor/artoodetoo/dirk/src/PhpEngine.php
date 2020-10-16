<?php

declare(strict_types=1);

namespace R2\Templating;

class PhpEngine
{
    protected $composers;
    protected $views;
    protected $ext;
    protected $separator;
    protected $blocks;
    protected $blockStack;

    /**
     * Constructor
     * @param array $config
     */
    public function __construct(array $config = [], array $composers = [])
    {
        $this->views      = $config['views']     ?? '.';
        $this->ext        = $config['ext']       ?? '.php';
        $this->separator  = $config['separator'] ?? '/';
        $this->blocks     = [];
        $this->blockStack = [];
        $this->composers  = [];

        foreach ($composers as $name => $composer) {
            $this->composer($name, $composer);
        }
    }

    /**
     * Add view composer
     * @param mixed $name     template name or array of names
     * @param mixed $composer data in the same meaning as for fetch() call, or callable returning such data
     */
    public function composer($name, $composer): void
    {
        if (\is_array($name)) {
            foreach ($name as $n) {
                $this->composer($n, $composer);
            }
        } else {
            $p = '~^'
                . \str_replace('\*', '[^' . $this->separator . ']+', \preg_quote($name, $this->separator . '~'))
                . '$~';
            $this->composers[$p][] = $composer;
        }
    }

    /**
     * Prepare file to include
     * @param  string $name
     * @return string
     */
    protected function prepare(string $name): string
    {
        if ('/' !== $this->separator) {
            $name = \str_replace($this->separator, '/', $name);
        }

        return $this->views . '/' . $name . $this->ext;
    }


    /**
     * Print result of templating
     * @param string $name
     * @param array  $data
     */
    public function render(string $name, array $data = []): string
    {
        echo $this->fetch($name, $data);
    }

    /**
     * Return result of templating
     * @param  string $name
     * @param  array  $data
     * @return string
     */
    public function fetch(string $name, array $data = []): string
    {
        $this->templates[] = $name;

        if (! empty($data)) {
            \extract($data);
        }

        while ($_name = \array_shift($this->templates)) {
            $this->beginBlock('content');

            foreach ($this->composers as $_cname => $_cdata) {
                if (\preg_match($_cname, $_name)) {
                    foreach ($_cdata as $_citem) {
                        \extract((\is_callable($_citem) ? $_citem($this) : $_citem) ?: []);
                    }
                }
            }

            require($this->prepare($_name));

            $this->endBlock(true);
        }

        return $this->block('content');
    }

    /**
     * Is template file exists?
     * @param  string  $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        return \file_exists($this->prepare($name));
    }

    /**
     * Define parent
     * @param string $name
     */
    protected function extend(string $name): void
    {
        $this->templates[] = $name;
    }

    /**
     * Return content of block if exists
     * @param  string $name
     * @param  string $default
     * @return string
     */
    protected function block(string $name, string $default = ''): string
    {
        return \array_key_exists($name, $this->blocks)
            ? $this->blocks[$name]
            : $default;
    }

    /**
     * Block begins
     * @param string $name
     */
    protected function beginBlock(string $name): void
    {
        \array_push($this->blockStack, $name);
        \ob_start();
    }

    /**
     * Block ends
     * @param boolean $overwrite
     * @return string
     */
    protected function endBlock(bool $overwrite = false): string
    {
        $name = \array_pop($this->blockStack);

        if (
            $overwrite
            || ! \array_key_exists($name, $this->blocks)
        ) {
            $this->blocks[$name] = \ob_get_clean();
        } else {
            $this->blocks[$name] .= \ob_get_clean();
        }

        return $name;
    }
}
