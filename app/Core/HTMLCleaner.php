<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use MioVisman\Jevix\Jevix;
use RuntimeException;

class HTMLCleaner extends Jevix
{
    protected $hConfigFile;
    protected $hConfigName;

    public function __construct(string $file)
    {
        if (! \is_file($file)) {
            throw new RuntimeException('File not found');
        }

        if (! \is_readable($file)) {
            throw new RuntimeException('File can not be read');
        }

        $this->hConfigFile = $file;
    }

    public function setConfig(string $name = 'default'): self
    {
        if (\is_string($this->hConfigName)) {
            if ($this->hConfigName !== $name) {
                throw new RuntimeException("A {$this->hConfigName} configuration has been installed, it cannot be replaced with an {$name} configuration");
            }
        } else {
            $this->configure($name, include $this->hConfigFile);

            $this->hConfigName = $name;
        }

        return $this;
    }

    protected function configure(string $name, array $config)
    {
        if (empty($config[$name])) {
            throw new RuntimeException("Configuration {$name} not found ");
        }

        foreach ($config[$name] as $method => $args) {
            if (
                ! \is_string($method)
                || 0 !== \strpos($method, 'cfg')
            ) {
                throw new RuntimeException("Bad method: {$method}");
            }

            if (\is_array($args)) {
                foreach ($args as $key => $value) {
                    if (
                        \is_string($key)
                        || ! \is_array($value)
                    ) {
                        $this->{$method}($value);
                    } else {
                        $this->{$method}(...$value);
                    }
                }
            } else {
                $this->{$method}($args);
            }
        }
    }
}
