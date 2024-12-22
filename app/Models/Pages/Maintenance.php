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

class Maintenance extends Page
{
    public function __construct(Container $container)
    {
        $container->Lang->load('common', $container->config->o_default_lang);

        parent::__construct($container);

        $this->identifier         = 'maintenance';
        $this->httpStatus         = 503;
        $this->nameTpl            = 'maintenance';
        $this->titles             = 'Maintenance';
        $this->maintenanceMessage = $this->c->config->o_maintenance_message;

        $this->header('Retry-After', '3600');
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
    }
}
