<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Container;

class EventDispatcher
{
    protected string $execFile;
    protected array $execArray;

    public function __construct(protected Container $c)
    {
        $this->execFile = $this->c->DIR_CONFIG . '/ext/exec.php';

        $this->init();
    }

    public function init(): self
    {
        if (\is_file($this->execFile)) {
            $arr = include $this->execFile;

            if (! empty($arr['autoload'])) {
                foreach ($arr['autoload'] as $prefix => $paths) {
                    $this->c->autoloader->addPsr4($prefix, $paths);
                }
            }

            if (! empty($arr['config'])) {
                $this->c->config($arr['config']);
            }

        } else {
            $arr = [];
        }

        $this->execArray = $arr;

        return $this;
    }
}
