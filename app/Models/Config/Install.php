<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;
use ForkBB\Models\Config\Model as Config;

class Install extends Method
{
    /**
     * Заполняет модель данными
     */
    public function install(): Config
    {
        $this->model->setAttrs($this->c->forConfig);

        return $this->model;
    }
}
