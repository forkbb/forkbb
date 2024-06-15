<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Controllers;

use ForkBB\Core\Container;
use ForkBB\Models\Page;

class Primary
{
    public function __construct(protected Container $c)
    {
    }

    /**
     * Проверка на запрос изображения
     * Проверка на обслуживание
     * Проверка на обновление
     * Проверка на бан
     */
    public function check(): ?Page
    {
        if (
            isset($_SERVER['HTTP_ACCEPT'][5])
            && false === \strpos($_SERVER['HTTP_ACCEPT'], 'text/')
            && false !== \strpos($_SERVER['HTTP_ACCEPT'], 'image/')
        ) {
            if (! $this->c->isInit('user')) {
                $this->c->user = $this->c->users->create(['id' => 0, 'group_id' => FORK_GROUP_GUEST]);
            }

            return $this->c->Message->message('Not Found', true, 404);
        }

        if (
            1 === $this->c->config->b_maintenance
            && ! $this->c->MAINTENANCE_OFF
        ) {
            if (
                ! isset($this->c->admins->list[$this->c->Cookie->uId])
                || ! isset($this->c->admins->list[$this->c->user->id])
            ) {
                if (! $this->c->isInit('user')) {
                    $this->c->user = $this->c->users->create(['id' => 0, 'group_id' => FORK_GROUP_GUEST]);
                }

                return $this->c->Maintenance;
            }
        }

        if ($this->c->config->i_fork_revision < $this->c->FORK_REVISION) {
            $confChange = [
                'multiple' => [
                    'CtrlRouting' => \ForkBB\Controllers\Update::class,
                    'AdminUpdate' => \ForkBB\Models\Pages\Admin\Update::class,
                ],
            ];

            $this->c->config($confChange);

            return null;
        }

        if ($this->c->bans->check($this->c->user)) {
            return $this->c->Ban->ban($this->c->user);
        }

        return null;
    }
}
