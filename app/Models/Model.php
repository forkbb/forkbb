<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use RuntimeException;

class Model
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
     * Вычисленные данные модели
     * @var array
     */
    protected $aCalc = [];

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
        return array_key_exists($name, $this->a) 
            || array_key_exists($name, $this->aCalc)
            || method_exists($this, 'get' . $name);
    }

    /**
     * Удаляет свойство
     *
     * @param mixed $name
     */
    public function __unset($name)
    {
        unset($this->a[$name]);     //????
        unset($this->aCalc[$name]); //????
    }

    /**
     * Устанавливает значение для свойства
     *
     * @param string $name
     * @param mixed $val
     */
    public function __set($name, $val)
    {
        unset($this->aCalc[$name]);

        if (method_exists($this, $method = 'set' . $name)) {
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
        if (array_key_exists($name, $this->aCalc)) {
            return $this->aCalc[$name];
        } elseif (method_exists($this, $method = 'get' . $name)) {
            return $this->aCalc[$name] = $this->$method();
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

    public function cens()
    {
        return $this->c->FuncAll->setModel('cens', $this);
    }

    public function num($decimals = 0)
    {
        return $this->c->FuncAll->setModel('num', $this, $decimals);
    }

    public function dt($dateOnly = false, $dateFormat = null, $timeFormat = null, $timeOnly = false, $noText = false)
    {
        return $this->c->FuncAll->setModel('dt', $this, $dateOnly, $dateFormat, $timeFormat, $timeOnly, $noText);
    }

    public function utc()
    {
        return $this->c->FuncAll->setModel('utc', $this);
    }
}
