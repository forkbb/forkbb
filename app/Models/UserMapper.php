<?php

namespace ForkBB\Models;

use ForkBB\Models\User;
use R2\DependencyInjection\ContainerInterface;
use RuntimeException;
use InvalidArgumentException;

class UserMapper
{
    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var DB
     */
    protected $db;

    /**
     * Конструктор
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->c = $container;
        $this->config = $container->get('config');
        $this->db = $container->get('DB');
    }

    /**
     * Возврат адреса пользователя
     * @return string
     */
    protected function getIpAddress()
    {
       return filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ?: 'unknow';
    }

    /**
     * Получение данных для текущего пользователя/гостя
     * @param int $id
     * @return User
     * @throws \RuntimeException
     */
    public function getCurrent($id = 1)
    {
        $ip = $this->getIpAddress();
        $id = (int) $id;

        if ($id > 1) {
            $result = $this->db->query('SELECT u.*, g.*, o.logged, o.idle FROM '.$this->db->prefix.'users AS u INNER JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id LEFT JOIN '.$this->db->prefix.'online AS o ON o.user_id=u.id WHERE u.id='.$id) or error('Unable to fetch user information', __FILE__, __LINE__, $this->db->error());
            $user = $this->db->fetch_assoc($result);
            $this->db->free_result($result);
        }
        if (empty($user['id'])) {
            $result = $this->db->query('SELECT u.*, g.*, o.logged, o.last_post, o.last_search FROM '.$this->db->prefix.'users AS u INNER JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id LEFT JOIN '.$this->db->prefix.'online AS o ON (o.user_id=1 AND o.ident=\''.$this->db->escape($ip).'\') WHERE u.id=1') or error('Unable to fetch guest information', __FILE__, __LINE__, $this->db->error());
            $user = $this->db->fetch_assoc($result);
            $this->db->free_result($result);
        }

        if (empty($user['id'])) {
            throw new RuntimeException('Unable to fetch guest information. Your database must contain both a guest user and a guest user group.');
        }

        $user['ip'] = $ip;
        return new User($user, $this->c);
    }

    /**
     * Обновляет время последнего визита для конкретного пользователя
     * @param int $id
     * @param int $time
     */
    public function updateLastVisit($id, $time)
    {
        $id = (int) $id;
        $time = (int) $time;
        $this->db->query('UPDATE '.$this->db->prefix.'users SET last_visit='.$time.' WHERE id='.$id) or error('Unable to update user visit data', __FILE__, __LINE__, $this->db->error());
    }

}
