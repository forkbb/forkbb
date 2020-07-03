<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;

class Method
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Модель
     * @var Model
     */
    protected $model;

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
     * Объявление модели
     *
     * @param Model $model
     *
     * @return Method
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;

        return $this;
    }
}
