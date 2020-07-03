<?php

namespace ForkBB\Controllers;

use ForkBB\Core\Container;
use ForkBB\Models\Page;

class Primary
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Проверка на обслуживание
     * Проверка на обновление
     * Проверка на бан
     *
     * @return Page|null
     */
    public function check(): ?Page
    {
        if ($this->c->config->o_maintenance && ! $this->c->MAINTENANCE_OFF) {
            if (
                ! isset($this->c->admins->list[$this->c->Cookie->uId])
                || ! isset($this->c->admins->list[$this->c->user->id])
            ) {
                if (! $this->c->isInit('user')) {
                    $this->c->user = $this->c->users->create(['id' => 1, 'group_id' => $this->c->GROUP_GUEST]);
                }

                return $this->c->Maintenance;
            }
        }

        if ($this->c->config->i_fork_revision < $this->c->FORK_REVISION) {
            \header('Location: db_update.php'); //????
            exit;
        }

        if (
            ! $this->c->user->isAdmin
            && $this->c->bans->check($this->c->user)
        ) {
            return $this->c->Ban->ban($this->c->user);
        }

        return null;
    }
}
