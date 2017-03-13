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
     * @return Page|null
     */
    public function check()
    {
        if ($this->c->config['o_maintenance'] && ! $this->c->MAINTENANCE_OFF) {
           if (! in_array($this->c->UserCookie->id(), $this->c->admins)
               || ! in_array($this->c->user['id'], $this->c->admins)
           ) {
               return $this->c->Maintenance;
           }
        }

        if (empty($this->c->config['i_fork_revision'])
            || $this->c->config['i_fork_revision'] < $this->c->FORK_REVISION
        ) {
            header('Location: db_update.php'); //????
            exit;
        }

        $ban = $this->c->CheckBans->check();
        if (is_array($ban)) {
            return $this->c->Ban->ban($ban);
        }
    }
}
