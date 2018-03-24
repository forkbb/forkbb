<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use RuntimeException;

class Rules extends Model
{
    /**
     * Флаг готовности
     * @var bool
     */
    protected $ready = false;

    /**
     * Возвращает значение свойства
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (true === $this->ready) {
            return parent::__get($name);
        } else {
            throw new RuntimeException('The model of rules isn\'t ready');
        }
    }
}
