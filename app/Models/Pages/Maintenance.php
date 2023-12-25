<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;
use ForkBB\Models\Page;
use function \ForkBB\__;

class Maintenance extends Page
{
    public function __construct(Container $container)
    {
        $container->Lang->load('common', $container->config->o_default_lang);

        parent::__construct($container);

        $this->identifier         = 'maintenance';
        $this->httpStatus         = 503;
        $this->nameTpl            = 'maintenance';
#       $this->onlinePos          = null; //????
#       $this->robots             = 'noindex';
        $this->titles             = 'Maintenance';
#       $this->fNavigation        = null; //????
        $this->maintenanceMessage = $this->c->config->o_maintenance_message;

        $this->header('Retry-After', '3600');

#        $this->fIswev = [FORK_MESS_INFO, $this->c->config->o_maintenance_message];
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
    }
}
