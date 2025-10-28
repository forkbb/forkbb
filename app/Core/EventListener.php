<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Container;
use ForkBB\Core\Event;

class EventListener
{
    protected array $eventList;

    public function __construct(protected Container $c)
    {
    }

    public function listen(Event $event): bool
    {
        $name = $event->getName();

        if (empty($this->eventList[$name])) {
            return false;
        }

        $listener = $this->eventList[$name];

        return $this->$listener($event);
    }
}
