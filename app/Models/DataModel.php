<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;
use InvalidArgumentException;
use RuntimeException;

class DataModel extends Model
{
    /**
     * Массив флагов измененных свойств модели
     * @var array
     */
    protected $modified = [];

    /**
     * Устанавливает значения для свойств
     *
     * @param array $attrs
     *
     * @return DataModel
     */
    public function setAttrs(array $attrs)
    {
        $this->a        = $attrs; //????
        $this->aCalc    = [];
        $this->modified = [];
        return $this;
    }

    /**
     * Перезапись свойст модели
     * 
     * @param array $attrs
     * 
     * @return DataModel
     */
    public function replAttrs(array $attrs)
    {
        foreach ($attrs as $key => $val) {
            $this->{'__' . $key} = $val; //????
            unset($this->aCalc['key']);
        }

        $modified = array_diff(array_keys($this->modified), array_keys($attrs));
        $this->modified = [];
        foreach ($modified as $key) {
            $this->modified[$key] = true;
        }

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
        // без отслеживания
        if (strpos($name, '__') === 0) {
            $name = substr($name, 2);
        // с отслеживанием
        } else {
            $this->modified[$name] = true;
        }
        return parent::__set($name, $val);
    }
}
