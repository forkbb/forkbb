<?php

namespace ForkBB\Models;

use ForkBB\Core\Model; //????
use R2\DependencyInjection\ContainerInterface;
use RuntimeException;

class User extends Model
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
     * @var UserCookie
     */
    protected $userCookie;

    /**
     * @var DB
     */
    protected $db;

    /**
     * Конструктор
     */
    public function __construct(array $config, $cookie, $db, ContainerInterface $container)
    {
        $this->config = $config;
        $this->userCookie = $cookie;
        $this->db = $db;
        $this->c = $container;
    }

    /**
     * @return User
     */
    public function init()
    {
        $this->current = $this->c->get('LoadCurrentUser')->load();

        return $this;
    }


    /**
     * Выход
     */
    public function logout()
    {
        if ($this->current['is_guest']) {
            return;
        }

        $this->userCookie->deleteUserCookie();
        $this->c->get('Online')->delete($this);
        // Update last_visit (make sure there's something to update it with)
        if (isset($this->current['logged'])) {
            $this->db->query('UPDATE '.$this->db->prefix.'users SET last_visit='.$this->current['logged'].' WHERE id='.$this->current['id']) or error('Unable to update user visit data', __FILE__, __LINE__, $this->db->error());
        }
    }

    /**
     * Вход
     * @param string $name
     * @param string $password
     * @param bool $save
     * @return mixed
     */
    public function login($name, $password, $save)
    {
        $result = $this->db->query('SELECT u.id, u.group_id, u.username, u.password, u.registration_ip, g.g_moderator FROM '.$this->db->prefix.'users AS u LEFT JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id WHERE u.username=\''.$this->db->escape($name).'\'') or error('Unable to fetch user info', __FILE__, __LINE__, $this->db->error());
        $user = $this->db->fetch_assoc($result);
        $this->db->free_result($result);

        if (empty($user['id'])) {
            return false;
        }

        $authorized = false;
        // For FluxBB by Visman 1.5.10.74 and above
        if (strlen($user['password']) == 40) {
            if (hash_equals($user['password'], sha1($password . $this->c->getParameter('SALT1')))) {
                $authorized = true;

                $user['password'] = password_hash($password, PASSWORD_DEFAULT);
                $this->db->query('UPDATE '.$this->db->prefix.'users SET password=\''.$this->db->escape($user['password']).'\' WHERE id='.$user['id']) or error('Unable to update user password', __FILE__, __LINE__, $this->db->error());
            }
        } else {
            $authorized = password_verify($password, $user['password']);
        }

        if (! $authorized) {
            return false;
        }

        // Update the status if this is the first time the user logged in
        if ($user['group_id'] == PUN_UNVERIFIED)
        {
            $this->db->query('UPDATE '.$this->db->prefix.'users SET group_id='.$this->config['o_default_user_group'].' WHERE id='.$user['id']) or error('Unable to update user status', __FILE__, __LINE__, $this->db->error());

            $this->c->get('users_info update');
        }

        // перезаписываем ip админа и модератора - Visman
        if ($this->config['o_check_ip'] == '1' && $user['registration_ip'] != $this->current['ip'])
        {
            if ($user['g_id'] == PUN_ADMIN || $user['g_moderator'] == '1')
                $this->db->query('UPDATE '.$this->db->prefix.'users SET registration_ip=\''.$this->db->escape($this->current['ip']).'\' WHERE id='.$user['id']) or error('Unable to update user IP', __FILE__, __LINE__, $this->db->error());
        }

        $this->c->get('Online')->delete($this);

        $this->c->get('UserCookie')->setUserCookie($user['id'], $user['password'], $save);

        return $user['id'];
    }

}
