<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;

class ManagerModel extends Model
{
    /**
     * @var array
     */
    protected $repository = [];

    public function get($key)
    {
        return isset($this->repository[$key]) ? $this->repository[$key] : null;
    }

    public function set($key, $value)
    {
        $this->repository[$key] = $value;

        return $this;
    }

    /**
     * Возвращает action по его имени
     * 
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $key = str_replace(['ForkBB\\Models\\', 'ForkBB\\', '\\'], '', get_class($this));

        return $this->c->{$key . $name}->setManager($this);
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

        return $this->c->{$key . ucfirst($name)}->setManager($this)->$name(...$args);
    }
}
