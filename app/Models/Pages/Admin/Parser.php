<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Pages\Admin;
use function \ForkBB\__;

abstract class Parser extends Admin
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->aIndex = 'parser';

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_parser');
    }
}
