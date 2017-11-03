<?php

namespace ForkBB\Controllers;

use ForkBB\Core\Container;

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
    public function check()
    {
        if ($this->c->config->o_maintenance && ! $this->c->MAINTENANCE_OFF) {
            if (! in_array($this->c->Cookie->uId, $this->c->admins->list) //????
                || ! in_array($this->c->user->id, $this->c->admins->list) //????
            ) {
                return $this->c->Maintenance;
            }
        }

        if ($this->c->config->i_fork_revision < $this->c->FORK_REVISION
        ) {
            header('Location: db_update.php'); //????
            exit;
        }

        if (! $this->c->user->isAdmin && $this->c->bans->check($this->c->user)) {
            return $this->c->Ban->ban($this->c->user);
        }
    }
}
