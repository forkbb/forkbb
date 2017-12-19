<?php

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
     * Объявление менеджера
     * 
     * @param ManagerModel $manager
     * 
     * @return Action
     */
    public function setManager(ManagerModel $manager)
    {
        $this->manager = $manager;
        return $this;
    }
}
