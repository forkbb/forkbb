<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\Model;

class Method
{
    protected Model $model;

    public function __construct(protected Container $c)
    {
    }

    /**
     * Объявление модели
     */
    public function setModel(Model $model): Method
    {
        $this->model = $model;

        return $this;
    }
}
