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
    protected string $autoFile;
    protected string $configFile;

    public function __construct(protected Container $c)
    {
        $this->autoFile   = $this->c->DIR_CONFIG . '/ext/auto.php';
        $this->configFile = $this->c->DIR_CONFIG . '/ext/config.php';

        $this->init();
    }

    public function init(): self
    {
        if (\is_file($this->autoFile)) {
            $arr = include $this->autoFile;

            if (! empty($arr)) {
                foreach ($arr as $prefix => $paths) {
                    $this->c->autoloader->addPsr4($prefix, $paths);
                }
            }
        }

        if (\is_file($this->configFile)) {
            $arr = include $this->configFile;

            if (! empty($arr)) {
                $this->c->config($arr);
            }
        }

        return $this;
    }
}
