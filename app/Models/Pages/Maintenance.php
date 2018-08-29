<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;
use ForkBB\Models\Page;

class Maintenance extends Page
{
    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $container->Lang->load('common', $container->config->o_default_lang);

        parent::__construct($container);

        $this->httpStatus         = 503;
        $this->nameTpl            = 'maintenance';
#       $this->onlinePos          = null; //????
#       $this->robots             = 'noindex';
        $this->titles             = \ForkBB\__('Maintenance');
#       $this->fNavigation        = null; //????
        $this->maintenanceMessage = $this->c->config->o_maintenance_message;

        $this->header('Retry-After', '3600');
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare()
    {
    }
}
