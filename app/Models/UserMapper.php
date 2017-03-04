<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;
use ForkBB\Models\User;
use RuntimeException;
use InvalidArgumentException;

class UserMapper
{
    /**
     * Контейнер
     * @var Container
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
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
        $this->config = $container->config;
        $this->db = $container->DB;
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
     * @param User $user
     */
    public function updateLastVisit(User $user)
    {
        if ($user->isLogged) {
            $this->db->query('UPDATE '.$this->db->prefix.'users SET last_visit='.$user->logged.' WHERE id='.$user->id) or error('Unable to update user visit data', __FILE__, __LINE__, $this->db->error());
        }
    }

    /**
     * Получение пользователя по условию
     * @param int|string
     * @param string $field
     * @return int|User
     * @throws \InvalidArgumentException
     */
    public function getUser($value, $field = 'id')
    {
        switch ($field) {
            case 'id':
                $where = 'u.id=' . (int) $value;
                break;
            case 'username':
                $where = 'u.username=\'' . $this->db->escape($value) . '\'';
                break;
            case 'email':
                $where = 'u.email=\'' . $this->db->escape($value) . '\'';
                break;
            default:
                throw new InvalidArgumentException('Field not supported');
        }
        $result = $this->db->query('SELECT u.*, g.* FROM '.$this->db->prefix.'users AS u LEFT JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id WHERE '.$where) or error('Unable to fetch user information', __FILE__, __LINE__, $this->db->error());

        // найдено несколько пользователей
        if ($this->db->num_rows($result) !== 1) {
            return $this->db->num_rows($result);
        }

        $user = $this->db->fetch_assoc($result);
        $this->db->free_result($result);

        // найден гость
        if ($user['id'] == 1) {
            return 1;
        }

        return new User($user, $this->c);
    }

    /**
     * Проверка на уникальность имени пользователя
     * @param string $username
     * @return bool
     */
    public function isUnique($username)
    {
        $result = $this->db->query('SELECT username FROM '.$this->db->prefix.'users WHERE (UPPER(username)=UPPER(\''.$this->db->escape($username).'\') OR UPPER(username)=UPPER(\''.$this->db->escape(preg_replace('%[^\p{L}\p{N}]%u', '', $username)).'\'))') or error('Unable to fetch user info', __FILE__, __LINE__, $this->db->error());
        return ! $this->db->num_rows($result);
    }

    /**
     * Обновить данные пользователя
     * @param int $id
     * @param array $update
     */
    public function updateUser($id, array $update)
    {
        $id = (int) $id;
        if ($id < 2 || empty($update)) {
            return;
        }

        $set = [];
        foreach ($update as $field => $value) {
            if (! is_string($field) || (null !== $value && ! is_int($value) && ! is_string($value))) {
                return;
            }
            if (null === $value) {
                $set[] = $field . '= NULL';
            } else {
                $set[] = $field . '=' . (is_int($value) ? $value : '\'' . $this->db->escape($value) . '\'');
            }
        }

        $this->db->query('UPDATE '.$this->db->prefix.'users SET '.implode(', ', $set).' WHERE id='.$id) or error('Unable to update user data', __FILE__, __LINE__, $this->db->error());
    }

    /**
     * Создание нового пользователя
     * @param User $user
     * @throws
     * @return int
     */
    public function newUser(User $user)
    {
        $this->db->query('INSERT INTO '.$this->db->prefix.'users (username, group_id, password, email, email_confirmed, email_setting, timezone, dst, language, style, registered, registration_ip, activate_string, u_mark_all_read) VALUES(\''.$this->db->escape($user->username).'\', '.$user->groupId.', \''.$this->db->escape($user->password).'\', \''.$this->db->escape($user->email).'\', '.$user->emailConfirmed.', '.$this->config['o_default_email_setting'].', '.$this->config['o_default_timezone'].' , '.$this->config['o_default_dst'].', \''.$this->db->escape($user->language).'\', \''.$user->style.'\', '.time().', \''.$this->db->escape($this->getIpAddress()).'\', \''.$this->db->escape($user->activateString).'\', '.$user->uMarkAllRead.')') or error('Unable to create user', __FILE__, __LINE__, $this->db->error());
        $new_uid = $this->db->insert_id(); //????
        return $new_uid;
    }
}
