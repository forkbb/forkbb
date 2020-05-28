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
        return $this->repository[$key] ?? null;
    }

    public function set($key, $value): self
    {
        $this->repository[$key] = $value;

        return $this;
    }

    public function isset($key): bool
    {
        return \array_key_exists($key, $this->repository);
    }

    /**
     * Возвращает action по его имени
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        $key = \str_replace(['ForkBB\\Models\\', 'ForkBB\\', '\\'], '', \get_class($this));

        return $this->c->{$key . \ucfirst($name)}->setManager($this);
    }

    /**
     * Выполняет подгружаемый метод при его наличии
     *
     * @param string $name
     * @param array $args
     *
     * @return mixed
     */
    public function __call(string $name, array $args)
    {
        return $this->__get($name)->$name(...$args);
    }
}
