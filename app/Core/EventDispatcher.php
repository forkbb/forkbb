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

class EventDispatcher
{
    protected string $autoFile;
    protected string $configFile;
    protected string $eventsFile;
    protected array $eventList = [];
    protected array $listeners = [];

    public function __construct(protected Container $c)
    {
        $this->autoFile   = $this->c->DIR_CONFIG . '/ext/auto.php';
        $this->configFile = $this->c->DIR_CONFIG . '/ext/config.php';
        $this->eventsFile = $this->c->DIR_CONFIG . '/ext/events.php';

        $this->init();
    }

    public function init(): self
    {
        if (\is_file($this->autoFile)) {
            $arr = include $this->autoFile;

            if (! empty($arr)) {
                foreach ($arr as $prefix => $paths) {
                    $this->c->autoloader->addPsr4($prefix, $paths);
                }
            }
        }

        if (\is_file($this->configFile)) {
            $arr = include $this->configFile;

            if (! empty($arr)) {
                $this->c->config($arr);
            }
        }

        if (\is_file($this->eventsFile)) {
            $this->eventList = include $this->eventsFile;
        }

        return $this;
    }

    public function dispatch(Event $event): void
    {
        $name = $event->getName();

        if (empty($this->eventList[$name])) {
            return;
        }

        foreach ($this->eventList[$name] as $listener) {
            if (empty($this->listeners[$listener])) {
                $this->listeners[$listener] = new $listener($this->c);
            }

            $this->listeners[$listener]->listen($event);

            if (true === $event->isStopped()) {
                return;
            }
        }
    }
}
