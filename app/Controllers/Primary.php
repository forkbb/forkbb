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
        $config = $this->c->config;

        // Проверяем режим обслуживания форума
        if ($config['o_maintenance'] && ! defined('PUN_TURN_OFF_MAINT')) { //????
           if (! in_array($this->c->UserCookie->id(), $this->c->admins)
               || ! in_array($this->c->user['id'], $this->c->admins)
           ) {
               return $this->c->Maintenance;
           }
        }

        // Обновляем форум, если нужно
        if (empty($config['i_fork_revision']) || $config['i_fork_revision'] < FORK_REVISION) {
            header('Location: db_update.php'); //????
            exit;
        }

        if (($banned = $this->c->CheckBans->check()) !== null) {
            return $this->c->Ban->ban($banned);
        }
    }
}
