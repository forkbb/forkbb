<?php

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Container;
use RuntimeException;

abstract class RulesValidator
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Выбрасывает исключение при отсутствии метода
     */
    public function __call(string $name, array $args)
    {
        throw new RuntimeException($name . ' validator not found');
    }
}
