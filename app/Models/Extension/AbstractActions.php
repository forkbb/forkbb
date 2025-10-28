<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Extension;

use ForkBB\Core\Container;

abstract class AbstractActions
{
    public function __construct(protected Container $c)
    {
    }

    abstract public function install(): bool;
    abstract public function uninstall(): bool;
    abstract public function updown(): bool;
    abstract public function enable(): bool;
    abstract public function disable(): bool;
}
