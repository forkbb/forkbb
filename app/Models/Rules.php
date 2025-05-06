<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models;

use ForkBB\Models\Model;
use RuntimeException;

class Rules extends Model
{
    /**
     * Флаг готовности
     */
    protected bool $ready = false;

    /**
     * Возвращает значение свойства
     */
    public function __get(string $name): mixed
    {
        if (true === $this->ready) {
            return parent::__get($name);

        } else {
            throw new RuntimeException('The model of rules isn\'t ready');
        }
    }
}
