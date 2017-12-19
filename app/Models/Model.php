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
     * @return mixed
     */
    public function __call($name, array $args)
    {
        $key = str_replace(['ForkBB\\Models\\', 'ForkBB\\', '\\'], '', get_class($this));

        return $this->c->{$key . ucfirst($name)}->setModel($this)->$name(...$args);
    }
}
