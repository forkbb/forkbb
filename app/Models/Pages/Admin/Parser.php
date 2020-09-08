<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Pages\Admin;
use function \ForkBB\__;

abstract class Parser extends Admin
{
    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->aIndex = 'parser';

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_parser');
    }
}
