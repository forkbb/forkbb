<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use stdClass;

class Event extends stdClass
{
    protected bool $propagationStopped = false;

    public function __construct(protected string $eventName)
    {
    }

    public function getName(): string
    {
        return $this->eventName;
    }

    public function isStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
