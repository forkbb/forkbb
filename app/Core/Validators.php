<?php

namespace ForkBB\Core;

use ForkBB\Core\Container;
use RuntimeException;

abstract class Validators
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

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
     * Выбрасывает исключение при отсутствии метода
     *
     * @param string $name
     * @param array $args
     *
     * @throws RuntimeException
     */
    public function __call($name, array $args)
    {
        throw new RuntimeException($name . ' validator not found');
    }
}
