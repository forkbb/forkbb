<?php

namespace ForkBB\Core;

abstract class AbstractModel
{
    /**
     * Данные модели
     * @var array
     */
    protected $data;

    abstract protected function beforeConstruct(array $data);

    /**
     * Конструктор
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $data = $this->beforeConstruct($data);
        foreach ($data as $key => $val) {
            if (is_string($key)) {
                $this->data[$this->camelCase($key)] = $val;
            }
        }
    }

    /**
     * Проверяет наличие свойства
     * @param mixed $key
     * @return bool
     */
    public function __isset($key)
    {
        return is_string($key) && isset($this->data[$key]);
    }

    /**
     * Удаляет свойство
     * @param mixed $key
     */
    public function __unset($key)
    {
        if (is_string($key)) {
            unset($this->data[$key]);
        }
    }

    /**
     * Устанавливает значение для свойства
     * При $key = __attrs ожидаем массив в $val
     * @param mixed $key
     * @param mixed $vak
     */
    public function __set($key, $val)
    {
        if ('__attrs' === $key) {
            if (is_array($val)) {
                foreach ($val as $x => $y) {
                    $x = $this->camelCase($x);
                    $this->$x = $y;
                }
            }
        } elseif (is_string($key)) {
            $method = 'set' . ucfirst($key);
            if (method_exists($this, $method)) {
                $this->$method($val);
            } else {
                $this->data[$key] = $val;
            }
        }
    }

    /**
     * Возвращает значение свойства
     * При $key = __attrs возвращает все свойства
     * @param mixed $key
     * @return mixed
     */
    public function __get($key)
    {
        if ('__attrs' === $key) {
            $data = [];
            foreach ($this->data as $x => $y) {
                $data[$this->underScore($x)] = $y; //????
            }
            return $data;
        } elseif (is_string($key)) {
            $method = 'get' . ucfirst($key);
            if (method_exists($this, $method)) {
                return $this->$method();
            } else {
                return isset($this->data[$key]) ? $this->data[$key] : null;
            }
        }
    }

    /**
     * Преобразует строку в camelCase
     * @param string $str
     * @return string
     */
    protected function camelCase($str)
    {
       return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }

    /**
     * Преобразует строку в under_score
     * @param string $str
     * @return string
     */
    protected function underScore($str)
    {
       return preg_replace('%([A-Z])%', function($match) {
           return '_' . strtolower($match[1]);
       }, $str);
    }
}
