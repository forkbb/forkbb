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
     * Конструктор
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
        $this->config = $container->config;
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
        $user = null;
        if ($id > 1) {
            $user = $this->c->DB->query('SELECT u.*, g.*, o.logged, o.idle FROM ::users AS u INNER JOIN ::groups AS g ON u.group_id=g.g_id LEFT JOIN ::online AS o ON o.user_id=u.id WHERE u.id=?i:id', [':id' => $id])->fetch();
        }
        if (empty($user['id'])) {
            $user = $this->c->DB->query('SELECT u.*, g.*, o.logged, o.last_post, o.last_search FROM ::users AS u INNER JOIN ::groups AS g ON u.group_id=g.g_id LEFT JOIN ::online AS o ON (o.user_id=1 AND o.ident=?s:ip) WHERE u.id=1', [':ip' => $ip])->fetch();
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
            $this->c->DB->exec('UPDATE ::users SET last_visit=?i:loggid WHERE id=?i:id', [':loggid' => $user->logged, ':id' => $user->id]);
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
                $where = 'u.id= ?i';
                break;
            case 'username':
                $where = 'u.username= ?s';
                break;
            case 'email':
                $where = 'u.email= ?s';
                break;
            default:
                throw new InvalidArgumentException('Field not supported');
        }
        $result = $this->c->DB->query('SELECT u.*, g.* FROM ::users AS u LEFT JOIN ::groups AS g ON u.group_id=g.g_id WHERE ' . $where, [$value])->fetchAll();
        // найдено несколько пользователей
        if (count($result) !== 1) {
            return count($result);
        }
        // найден гость
        if ($result[0]['id'] == 1) {
            return 1;
        }
        return new User($result[0], $this->c);
    }

    /**
     * Проверка на уникальность имени пользователя
     * @param string $username
     * @return bool
     */
    public function isUnique($username)
    {
        $vars = [
            ':name' => $username,
            ':other' => preg_replace('%[^\p{L}\p{N}]%u', '', $username),
        ];
        $result = $this->c->DB->query('SELECT username FROM ::users WHERE UPPER(username)=UPPER(?s:name) OR UPPER(username)=UPPER(?s:other)', $vars)->fetchAll();
        return ! count($result);
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

        $set = $vars = [];
        foreach ($update as $field => $value) {
            $vars[] = $value;
            if (is_int($value)) {
                $set[] = $field . ' = ?i';
            } else {
                $set[] = $field . ' = ?s';
            }
        }
        $vars[] = $id;
        $this->c->DB->query('UPDATE ::users SET ' . implode(', ', $set) . ' WHERE id=?i', $vars); //????
    }

    /**
     * Создание нового пользователя
     * @param User $user
     * @throws
     * @return int
     */
    public function newUser(User $user)
    {
        $vars = [
            ':name' => $user->username,
            ':group' => $user->groupId,
            ':password' => $user->password,
            ':email' => $user->email,
            ':confirmed' => $user->emailConfirmed,
            ':setting' => $this->config['o_default_email_setting'],
            ':timezone' => $this->config['o_default_timezone'],
            ':dst' => $this->config['o_default_dst'],
            ':language' => $user->language,
            ':style' => $user->style,
            ':registered' => time(),
            ':ip' => $this->getIpAddress(),
            ':activate' => $user->activateString,
            ':mark' => $user->uMarkAllRead,
        ];
        $this->c->DB->query('INSERT INTO ::users (username, group_id, password, email, email_confirmed, email_setting, timezone, dst, language, style, registered, registration_ip, activate_string, u_mark_all_read) VALUES(?s:name, ?i:group, ?s:password, ?s:email, ?i:confirmed, ?i:setting, ?s:timezone, ?i:dst, ?s:language, ?s:style, ?i:registered, ?s:ip, ?s:activate, ?i:mark)', $vars);
        return $this->c->DB->lastInsertId();
    }
}
