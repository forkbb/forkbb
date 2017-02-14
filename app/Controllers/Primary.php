<?php

namespace ForkBB\Controllers;

use R2\DependencyInjection\ContainerInterface;

class Primary
{
    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    /**
     * Конструктор
     * @param array $config
     */
    public function __construct(ContainerInterface $container)
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
        $config = $this->c->get('config');

        // Проверяем режим обслуживания форума
        if ($config['o_maintenance'] && ! defined('PUN_TURN_OFF_MAINT')) { //????
           if (! in_array($this->c->get('UserCookie')->id(), $this->c->get('admins'))
               || ! in_array($this->c->get('user')['id'], $this->c->get('admins'))
           ) {
               return $this->c->get('Maintenance');
           }
        }

        // Обновляем форум, если нужно
        if (empty($config['i_fork_revision']) || $config['i_fork_revision'] < FORK_REVISION) {
            header('Location: db_update.php'); //????
            exit;
        }

        if (($banned = $this->c->get('CheckBans')->check($this->c->get('user'))) !== null) {
            return $this->c->get('Ban')->ban($banned);
        }
    }
}
