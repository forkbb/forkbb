<?php

namespace R2\DependencyInjection;

use InvalidArgumentException;
use R2\DependencyInjection\ContainerInterface;
use R2\DependencyInjection\ContainerAwareInterface;


/**
 * Service Container
 */
class Container implements ContainerInterface
{
    private $instances = [];
    private $config = [ 'shared' => [], 'multiple'  => [] ];

    public function __construct(array $config = null)
    {
        if (!empty($config)) {
            $this->config($config);
        }
    }

    public function config(array $config)
    {
        $this->config = array_replace_recursive($this->config, $config);
    }

    /**
     * Gets a service.
     *
     * @param string $id The service identifier
     *
     * @return mixed The associated service
     */
    public function get($id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        $toShare = false;
        if (isset($this->config['shared'][$id])) {
            $toShare = true;
            $config = (array) $this->config['shared'][$id];
        } elseif (isset($this->config['multiple'][$id])) {
            $config = (array) $this->config['multiple'][$id];
        } else {
            throw new \InvalidArgumentException('Wrong property name '.$id);
        }
        // N.B. "class" is just the first element, regardless of its key
        $class = array_shift($config);
        $args = [];
        // If you want to susbtitute some values in arguments, use non-numeric keys for them
        foreach ($config as $k => $v) {
            $args[] = is_numeric($k) ? $v : $this->resolve($v);
        }
        // Special case: reference to factory method
        if ($class{0} == '@' && strpos($class, ':') !== false) {
            list($factoryName, $methodName) = explode(':', substr($class, 1));
            $f = [$this->get($factoryName), $methodName]; /** @var string $f suppress IDE warning :( */
            $service = $f(...$args);
        } else {
            $service = new $class(...$args);
            if ($service instanceof ContainerAwareInterface) {
                $service->setContainer($this);
            }
        }
        if ($toShare) {
            $this->instances[$id] = $service;
        }
        return $service;
    }

    /**
     * Sets a service.
     * Provides a fluent interface.
     *
     * @param string $id      The service identifier
     * @param mixed  $service The service instance
     *
     * @return ContainerInterface Self reference
     */
    public function set($id, $service)
    {
        $this->instances[$id] = $service;
        return $this;
    }

    /**
     * Gets a parameter.
     *
     * @param string $name The parameter name
     *
     * @return mixed The parameter value
     */
    public function getParameter($name, $default = null)
    {
        $segments = explode('.', $name);
        $ptr =& $this->config;
        foreach ($segments as $s) {
            if (isset($ptr[$s])
                || (is_array($ptr) && array_key_exists($s, $ptr))
            ) {
                $ptr =& $ptr[$s];
            } else {
                return $default;
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
     * @return ContainerInterface Self reference
     */
    public function setParameter($name, $value)
    {
        $segments = explode('.', $name);
        $n = count($segments);
        $ptr =& $this->config;
        foreach ($segments as $s) {
            if (--$n) {
                if (!array_key_exists($s, $ptr)) {
                    $ptr[$s] = [];
                } elseif (!is_array($ptr[$s])) {
                    throw new \InvalidArgumentException("Scalar \"{$s}\" in the path \"{$name}\"");
                }
                $ptr =& $ptr[$s];
            } else {
                $ptr[$s] = $value;
            }
        }

        return $this;
    }

    protected function resolve($value)
    {
        if (is_string($value)) {
            if (strpos($value, '%') !== false) {
                // whole string substitution can return any type of value
                if (preg_match('~^%(\w+(?:\.\w+)*)%$~', $value, $matches)) {
                    $value = $this->getParameter($matches[1]);
                // partial string substitution casts value to string
                } else {
                    $value = preg_replace_callback('~%(\w+(?:\.\w+)*)%~',
                        function ($matches) {
                            return $this->getParameter($matches[1], '');
                        }, $value);
                }
            } elseif (isset($value{0}) && $value{0} === '@') {
                // получение данных из объекта как массива по индексу
                if (preg_match('~^@([\w-]+)\[(\w+)\]$~', $value, $matches)) {
                    $obj = $this->get($matches[1]);
                    return isset($obj[$matches[2]]) ? $obj[$matches[2]] : null;
                } else {
                    return $this->get(substr($value, 1));
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as &$v) {
                $v = $this->resolve($v);
            }
            unset($v);
        }
        return $value;
    }
}
