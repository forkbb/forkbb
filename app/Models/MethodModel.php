<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;

abstract class MethodModel
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
     * @param Model $model
     * @param Container $container
     */
    public function __construct(Model $model, Container $container)
    {
        $this->model = $model;
        $this->c = $container;
    }
}
