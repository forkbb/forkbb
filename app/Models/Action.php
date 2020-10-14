<?php

declare(strict_types=1);

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\ManagerModel;

class Action
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Модель
     * @var ManagerModel
     */
    protected $manager;

    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Объявление менеджера
     */
    public function setManager(ManagerModel $manager): Action
    {
        $this->manager = $manager;

        return $this;
    }
}
