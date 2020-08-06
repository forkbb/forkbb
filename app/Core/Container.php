<?php
/**
 * based on Container https://github.com/artoodetoo/container
 * by artoodetoo
 */
namespace ForkBB\Core;

use InvalidArgumentException;

/**
 * Service Container
 */
class Container
{
    protected $instances = [];
    protected $shared = [];
    protected $multiple = [];

    /**
     * Конструктор
     *
     * @param array config
     */
    public function __construct(array $config = null)
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
     *
     * @param array config
     */
    public function config(array $config): void
    {
        if (isset($config['shared'])) {
            $this->shared = \array_replace_recursive($this->shared, $config['shared']);
        }
        if (isset($config['multiple'])) {
            $this->multiple = \array_replace_recursive($this->multiple, $config['multiple']);
        }
        unset($config['shared'], $config['multiple']);
        if (! empty($config)) {
            $this->config = \array_replace_recursive($this->config, $config);
        }
    }

    /**
     * Gets a service or parameter.
     *
     * @param string $id
     *
     * @throws InvalidArgumentException
     *
     * @return mixed
     */
    public function __get(string $id)
    {
        if (\array_key_exists($id, $this->instances)) { //???? isset($this->instances[$id]) ||
            return $this->instances[$id];
        } elseif (false !== \strpos($id, '.')) {
            $tree    = \explode('.', $id);
            $service = $this->__get(\array_shift($tree));
            if (\is_array($service)) {
                return $this->fromArray($service, $tree);
            } elseif (\is_object($service)) {
                return $service->{$tree[0]};
            } else {
                return null;
            }
        }
        if (isset($this->shared[$id])) {
            $toShare = true;
            $config  = (array) $this->shared[$id];
        } elseif (isset($this->multiple[$id])) {
            $toShare = false;
            $config  = (array) $this->multiple[$id];
        } else {
            throw new InvalidArgumentException('Wrong property name: ' . $id);
        }
        // N.B. "class" is just the first element, regardless of its key
        $class = \array_shift($config);
        $args  = [];
        // If you want to susbtitute some values in arguments, use non-numeric keys for them
        foreach ($config as $k => $v) {
            $args[] = \is_numeric($k) ? $v : $this->resolve($v);
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
            $this->instances[$id] = $service;
        }

        return $service;
    }

    /**
     * Sets a service or parameter.
     * Provides a fluent interface.
     *
     * @param string $id
     * @param mixed $service
     */
    public function __set(string $id, $service): void
    {
        if (false !== \strpos($id, '.')) {
            //????
        } else {
            $this->instances[$id] = $service;
        }
    }

    /**
     * Gets data from array.
     *
     * @param array $array
     * @param array $tree
     *
     * @return mixed
     */
    public function fromArray(array $array, array $tree)
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
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     *
     * @throws InvalidArgumentException
     *
     * @return Container
     */
    public function setParameter(string $name, $value): Container
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

    protected function resolve($value)
    {
        if (\is_string($value)) {
            if (false !== \strpos($value, '%')) {
                // whole string substitution can return any type of value
                if (\preg_match('~^%([a-z0-9_]+(?:\.[a-z0-9_]+)*)%$~i', $value, $matches)) {
                    $value = $this->__get($matches[1]);
                } else {
                    // partial string substitution casts value to string
                    $value = \preg_replace_callback(
                        '~%([a-z0-9_]+(?:\.[a-z0-9_]+)*)%~i',
                        function ($matches) {
                            return $this->__get($matches[1]);
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
     * @param string $name
     *
     * @return bool
     */
    public function isInit(string $name): bool
    {
        return \array_key_exists($name, $this->instances);
    }
}
