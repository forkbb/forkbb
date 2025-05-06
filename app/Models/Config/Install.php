<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;
use ForkBB\Models\Config\Config;

class Install extends Method
{
    /**
     * Заполняет модель данными
     */
    public function install(): Config
    {
        $this->model->setModelAttrs($this->c->forConfig);

        return $this->model;
    }
}
