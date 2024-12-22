<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */
/**
 * based on Container <https://github.com/artoodetoo/container>
 *
 * @copyright (c) 2016 artoodetoo <https://github.com/artoodetoo>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use InvalidArgumentException;

/**
 * Service Container
 */
class Container
{
    protected array $instances = [];
    protected array $shared    = [];
    protected array $multiple  = [];

    public function __construct(array $config = [])
    {
        if (empty($config)) {
            return;
        }

        if (isset($config['shared'])) {
            $this->shared = $config['shared'];
        }

        if (isset($config['multiple'])) {
            $this->multiple = $config['multiple'];
        }

        unset($config['shared'], $config['multiple']);

        $this->instances = $config;
    }

    /**
     * Adding config
     */
    public function config(array $config): void
    {
        if (isset($config['shared'])) {
            $this->shared = \array_replace($this->shared, $config['shared']);
        }

        if (isset($config['multiple'])) {
            $this->multiple = \array_replace($this->multiple, $config['multiple']);
        }

        unset($config['shared'], $config['multiple']);

        if (! empty($config)) {
            $this->instances = \array_replace($this->instances, $config);
        }
    }

    /**
     * Gets a service or parameter.
     */
    public function __get(string $key): mixed
    {
        if (\array_key_exists($key, $this->instances)) {
            return $this->instances[$key];

        } elseif (false !== \strpos($key, '.')) {
            $tree    = \explode('.', $key);
            $service = $this->__get(\array_shift($tree));

            if (\is_array($service)) {
                return $this->fromArray($service, $tree);

            } elseif (\is_object($service)) {
                return $service->{$tree[0]};

            } else {
                return null;
            }
        }

        if (isset($this->shared[$key])) {
            $toShare = true;
            $config  = $this->shared[$key];

        } elseif (isset($this->multiple[$key])) {
            $toShare = false;
            $config  = $this->multiple[$key];

        } elseif (isset($this->shared["%{$key}%"])) {
            return $this->instances[$key] = $this->resolve($this->shared["%{$key}%"]);

        } else {
            throw new InvalidArgumentException("Wrong property name: {$key}");
        }

        $args = [];

        if (\is_array($config)) {
            // N.B. "class" is just the first element, regardless of its key
            $class = \array_shift($config);
            // If you want to susbtitute some values in arguments, use non-numeric keys for them
            foreach ($config as $k => $v) {
                $args[] = \is_numeric($k) ? $v : $this->resolve($v);
            }

        } else {
            $class = $config;
        }

        // Special case: reference to factory method
        if (
            '@' === $class[0]
            && false !== \strpos($class, ':')
        ) {
            list($name, $method) = \explode(':', \substr($class, 1), 2);

            $factory = $this->__get($name);
            $service = $factory->$method(...$args);

        } else {
            // Adding this container in the arguments for constructor
            $args[]  = $this;
            $service = new $class(...$args);
        }

        if ($toShare) {
            $this->instances[$key] = $service;
        }

        return $service;
    }

    /**
     * Sets a service or parameter.
     * Provides a fluent interface.
     */
    public function __set(string $key, mixed $service): void
    {
        if (false !== \strpos($key, '.')) {
            throw new InvalidArgumentException("Wrong property name: {$key}");

        } else {
            $this->instances[$key] = $service;
        }
    }

    /**
     * Gets data from array.
     */
    public function fromArray(array $array, array $tree): mixed
    {
        $ptr = &$array;

        foreach ($tree as $s) {
            if (isset($ptr[$s])) {
                $ptr = &$ptr[$s];

            } else {
                return null;
            }
        }

        return $ptr;
    }

    /**
     * Sets a parameter.
     * Provides a fluent interface.
     */
    public function setParameter(string $name, mixed $value): Container
    {
        $segments = \explode('.', $name);
        $n        = \count($segments);
        $ptr      = &$this->config;

        foreach ($segments as $s) {
            if (--$n) {
                if (! \array_key_exists($s, $ptr)) {
                    $ptr[$s] = [];

                } elseif (! \is_array($ptr[$s])) {
                    throw new InvalidArgumentException("Scalar '{$s}' in the path '{$name}'");
                }

                $ptr = &$ptr[$s];

            } else {
                $ptr[$s] = $value;
            }
        }

        return $this;
    }

    protected function resolve(mixed $value): mixed
    {
        if (\is_string($value)) {
            if (false !== \strpos($value, '%')) {
                // whole string substitution can return any type of value
                if (\preg_match('~^%([a-z0-9_]+(?:\.[a-z0-9_]+)*)%$~i', $value, $matches)) {
                    $value = $this->__get($matches[1]);

                } else {
                    // partial string substitution casts value to string
                    $value = \preg_replace_callback(
                        '~\\\%|%([a-z0-9_]+(?:\.[a-z0-9_]+)*)%~i',
                        function ($matches) {
                            return '\\%' == $matches[0] ? '%' : $this->__get($matches[1]);
                        },
                        $value
                    );
                }

            } elseif (
                isset($value[0])
                && '@' === $value[0]
            ) {
                return $this->__get(\substr($value, 1));
            }

        } elseif (\is_array($value)) {
            foreach ($value as &$v) {
                $v = $this->resolve($v);
            }

            unset($v);
        }

        return $value;
    }

    /**
     * Проверяет на наличие инициализированного экземпляра объекта
     */
    public function isInit(string $name): bool
    {
        return \array_key_exists($name, $this->instances);
    }
}
