<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use InvalidArgumentException;
use RuntimeException;

abstract class Model
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Данные модели
     * @var array
     */
    protected $a = [];

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Проверяет наличие свойства
     *
     * @param mixed $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->a[$name]); //???? array_key_exists($name, $this->a)
    }

    /**
     * Удаляет свойство
     *
     * @param mixed $name
     */
    public function __unset($name)
    {
        unset($this->a[$name]);
    }

    /**
     * Устанавливает значение для свойства
     *
     * @param string $name
     * @param mixed $val
     */
    public function __set($name, $val)
    {
        $method = 'set' . ucfirst($name);
        if (method_exists($this, $method)) {
            $this->$method($val);
        } else {
            $this->a[$name] = $val;
        }
    }

    /**
     * Возвращает значение свойства
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            return isset($this->a[$name]) ? $this->a[$name] : null;
        }
    }

    /**
     * Выполняет подгружаемый метод при его наличии
     *
     * @param string $name
     * @param array $args
     *
     * @throws RuntimeException
     *
     * @return mixed
     */
    public function __call($name, array $args)
    {
        $key = str_replace(['ForkBB\\', '\\'], '', get_class($this));

        if (empty($this->c->METHODS[$key][$name])) {
            throw new RuntimeException("The {$name} method was not found");
        }

        $link = explode(':', $this->c->METHODS[$key][$name], 2);
        $factory = new $link[0]($this, $this->c);

        if (isset($link[1])) {
            return $factory->{$link[1]}(...$args);
        } else {
            return $factory->$name(...$args);
        }
    }
}
