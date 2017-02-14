<?php

namespace ForkBB\Models;

use ForkBB\Models\User;
use RuntimeException;
use InvalidArgumentException;

class UserMapper
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var UserCookie
     */
    protected $cookie;

    /**
     * @var DB
     */
    protected $db

    /**
     * Конструктор
     * @param array $config
     * @param UserCookie $cookie
     * @param DB $db
     */
    public function __construct(array $config, $cookie, $db)
    {
        $this->config = $config;
        $this->cookie = $cookie;
        $this->db = $db;
    }

    /**
     * @param int $id
     *
     * @throws \InvalidArgumentException
     * @retrun User
     */
    public function load($id = null)
    {
        if (null === $id) {
            $user = $this->loadCurrent();
            return new User($user, $this->config, $this->cookie);
        } elseif ($id < 2) {
            throw new InvalidArgumentException('User id can not be less than 2');
        }


    }

    /**
     * @retrun array
     */
    protected function loadCurrent()
    {
        if (($userId = $this->cookie->id()) === false) {
            return $this->loadGuest();
        }

        $result = $this->db->query('SELECT u.*, g.*, o.logged, o.idle, o.witt_data FROM '.$this->db->prefix.'users AS u INNER JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id LEFT JOIN '.$this->db->prefix.'online AS o ON o.user_id=u.id WHERE u.id='.$userId) or error('Unable to fetch user information', __FILE__, __LINE__, $this->db->error());
        $user = $this->db->fetch_assoc($result);
        $this->db->free_result($result);

        if (empty($user['id']) || ! $this->cookie->verifyHash($user['id'], $user['password'])) {
            return $this->loadGuest();
        }

        // проверка ip админа и модератора - Visman
        if ($this->config['o_check_ip'] == '1' && ($user['g_id'] == PUN_ADMIN || $user['g_moderator'] == '1') && $user['registration_ip'] != get_remote_address())
        {
            return $this->loadGuest();
        }

        return $user;
    }

    /**
     * @throws \RuntimeException
     * @retrun array
     */
    protected function loadGuest()
    {
        $remote_addr = get_remote_address();
        $result = $this->db->query('SELECT u.*, g.*, o.logged, o.last_post, o.last_search, o.witt_data FROM '.$this->db->prefix.'users AS u INNER JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id LEFT JOIN '.$this->db->prefix.'online AS o ON o.ident=\''.$this->db->escape($remote_addr).'\' WHERE u.id=1') or error('Unable to fetch guest information', __FILE__, __LINE__, $this->db->error());
        $user = $this->db->fetch_assoc($result);
        $this->db->free_result($result);

        if (empty($user['id']) {
            throw new RuntimeException('Unable to fetch guest information. Your database must contain both a guest user and a guest user group.');
        }

        return $user;
    }
}
