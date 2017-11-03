<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use InvalidArgumentException;
use RuntimeException;

abstract class DataModel extends Model
{
    /**
     * Массив флагов измененных свойств модели
     * @var array
     */
    protected $modified = [];

    /**
     * Конструктор
     *
     * @param array $attrs
     * @param Container $container
     */
    public function __construct(array $attrs, Container $container)
    {
        parent::__construct($container);
        $this->a = $attrs;
    }

    /**
     * Устанавливает значения для свойств
     *
     * @param array $attrs
     *
     * @return DataModel
     */
    public function setAttrs(array $attrs)
    {
        $this->a = $attrs; //????
        $this->modified = [];
        return $this;
    }

    /**
     * Возвращает значения свойств в массиве
     *
     * @return array
     */
    public function getAttrs()
    {
        return $this->a; //????
    }

    /**
     * Возвращает массив имен измененных свойств модели
     *
     * @return array
     */
    public function getModified()
    {
        return array_keys($this->modified);
    }

    /**
     * Обнуляет массив флагов измененных свойств модели
     */
    public function resModified()
    {
        $this->modified = [];
    }

    /**
     * Устанавливает значение для свойства
     *
     * @param string $name
     * @param mixed $val
     */
    public function __set($name, $val)
    {
        // запись свойства без отслеживания изменений
        if (strpos($name, '__') === 0) {
            return parent::__set(substr($name, 2), $val);
        }

        $old = isset($this->a[$name]) ? $this->a[$name] : null;
        parent::__set($name, $val);
        if ($old !== $this->a[$name]) {
            $this->modified[$name] = true;
        }
    }
}
