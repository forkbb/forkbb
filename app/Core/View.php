<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */
/**
 * based on Dirk <https://github.com/artoodetoo/dirk>
 *
 * @copyright (c) 2015 artoodetoo <i.am@artoodetoo.org, https://github.com/artoodetoo>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\View\Compiler;
use ForkBB\Models\Page;
use RuntimeException;

class View
{
    protected string $ext = '.forkbb.php';

    protected ?Compiler $compilerObj;
    protected string    $compilerClass = Compiler::class;

    protected string $cache;
    protected string $defaultDir;
    protected string $defaultHash;

    protected array $other      = [];
    protected array $composers  = [];
    protected array $blocks     = [];
    protected array $blockStack = [];
    protected array $templates  = [];

    public function __construct(string|array $config, mixed $views)
    {
        if (\is_array($config)) {
            $this->cache      = $config['cache'];
            $this->defaultDir = $config['defaultDir'];

            $this->other[\hash('md5', $config['userDir'])] = [$config['userDir'], 10];

            if (! empty($config['composers'])) {
                foreach ($config['composers'] as $name => $composer) {
                    $this->composer($name, $composer);
                }
            }

            if (! empty($config['compiler'])) {
                $this->compilerClass = $config['compiler'];
            }
        } else {
            // для rev. 68 и ниже
            $this->cache       = $config;
            $this->defaultDir  = $views;
        }

        $this->defaultHash = \hash('md5', $this->defaultDir);
    }

    /**
     * Возвращает отображение страницы $p или null
     */
    public function rendering(Page $p): ?string
    {
        if (null === $p->nameTpl) {
            $this->sendHttpHeaders($p);

            return null;
        }

        $p->prepare();

        $this->templates[] = $p->nameTpl;

        while ($_name = \array_shift($this->templates)) {
            $this->beginBlock('content');

            foreach ($this->composers as $_cname => $_cdata) {
                if (\preg_match($_cname, $_name)) {
                    foreach ($_cdata as $_citem) {
                        \extract((\is_callable($_citem) ? $_citem($this) : $_citem) ?: []);
                    }
                }
            }

            require $this->prepare($_name);

            $this->endBlock(true);
        }

        $this->sendHttpHeaders($p);

        return $this->block('content');
    }

    /**
     * Отправляет HTTP заголовки
     */
    protected function sendHttpHeaders(Page $p): void
    {
        foreach ($p->httpHeaders as $catHeader) {
            foreach ($catHeader as $header) {
                \header($header[0], $header[1]);
            }
        }
    }

    /**
     * Возвращает отображение шаблона $name
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

            require $this->prepare($_name);

            $this->endBlock(true);
        }

        return $this->block('content');
    }

    /**
     * Add view composer
     * @param mixed $name     template name or array of names
     * @param mixed $composer data in the same meaning as for fetch() call, or callable returning such data
     */
    public function composer(string|array $name, mixed $composer): void
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
     * Подготавливает файл для подключения
     */
    protected function prepare(string $name): string
    {
        $st = \preg_replace('%\W%', '-', $name);

        foreach ($this->other as $hash => $cur) {
            if (\file_exist($tpl = "{$cur[0]}/{$name}{$this->ext}")) {
                $php = "{$this->cache}/_{$st}-{$hash}.php";

                if (
                    ! \file_exists($php)
                    || \filemtime($tpl) > \filemtime($php)
                ) {
                    $this->create($php, $tpl);
                }

                return $php;
            }
        }

        $hash = $this->defaultHash;
        $tpl  = "{$this->defaultDir}/{$name}{$this->ext}";
        $php  = "{$this->cache}/_{$st}-{$hash}.php";

        if (
            ! \file_exists($php)
            || \filemtime($tpl) > \filemtime($php)
        ) {
            $this->create($php, $tpl);
        }

        return $php;
    }

    /**
     * Генерирует $php файл на основе шаблона $tpl
     */
    protected function create(string $php, string $tpl): void
    {
        if (empty($this->compilerObj)) {
            $this->compilerObj = new $this->compilerClass();
        }

        $text = $this->compilerObj->create(\file_get_contents($tpl), \hash('fnv1a32', $tpl));

        if (false === \file_put_contents($php, $text, \LOCK_EX)) {
            throw new RuntimeException("Failed to write {$php} file");
        }

        if (\function_exists('\\opcache_invalidate')) {
            \opcache_invalidate($php, true);
        }
    }

    /**
     * Задает родительский шаблон
     */
    protected function extend(string $name): void
    {
        $this->templates[] = $name;
    }

    /**
     * Возвращает содержимое блока или $default
     */
    protected function block(string $name, string $default = ''): string
    {
        return \array_key_exists($name, $this->blocks)
            ? $this->blocks[$name]
            : $default;
    }

    /**
     * Задает начало блока
     */
    protected function beginBlock(string $name): void
    {
        $this->blockStack[] = $name;

        \ob_start();
    }

    /**
     * Задает конец блока
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
