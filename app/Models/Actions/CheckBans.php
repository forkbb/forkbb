<?php

namespace ForkBB\Models\Actions;

use ForkBB\Core\Container;

class CheckBans
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Содержит массив с описание бана для проверяемого юзера
     */
    protected $ban;

    /**
     * Конструктор
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Возвращает массив с описанием бана или null
     * @return null|array
     */
    public function check()
    {
        $user = $this->c->user;

        // Для админов и при отсутствии банов прекращаем проверку
        if ($user->isAdmin) {
            return null;
        } elseif ($user->isGuest) {
            $banned = $this->isBanned(null, null, $user->ip);
        } else {
            $banned = $this->isBanned($user->username, $user->email, $user->ip);
        }

        if ($banned) {
            $this->c->Online->delete($user); //???? а зачем это надо?
            return $this->ban;
        }

        return null;
    }

    /**
     * Проверяет наличие бана на основании имени юзера, email и(или) ip
     * Удаляет просроченные баны
     * @param string $username
     * @param string $email
     * @param string $userIp
     * @return int
     */
    public function isBanned($username = null, $email = null, $userIp = null)
    {
        $bans = $this->c->bans;
        if (empty($bans)) {
            return 0;
        }
        if (isset($username)) {
            $username = mb_strtolower($username, 'UTF-8');
        }
        if (isset($userIp)) {
            // Add a dot or a colon (depending on IPv4/IPv6) at the end of the IP address to prevent banned address
            // 192.168.0.5 from matching e.g. 192.168.0.50
            $add = strpos($userIp, '.') !== false ? '.' : ':';
            $userIp .= $add;
        }

        $banned = 0;
        $remove = [];
        $now = time();

        foreach ($bans as $cur) {
            if ($cur['expire'] != '' && $cur['expire'] < $now) {
                $remove[] = $cur['id'];
                continue;
            } elseif (isset($username) && $cur['username'] != '' && $username == mb_strtolower($cur['username'])) {
                $this->ban = $cur;
                $banned = 1;
                break;
            } elseif (isset($email) && $cur['email'] != '' && $email == $cur['email']) {
                $this->ban = $cur;
                $banned = 2;
                break;
            } elseif (isset($userIp) && $cur['ip'] != '') {
                foreach (explode(' ', $cur['ip']) as $ip) {
                    $ip .= $add;
                    if (substr($userIp, 0, strlen($ip)) == $ip) {
                        $this->ban = $cur;
                        $banned = 3;
                        break 2;
                    }
                }
            }
        }

        // If we removed any expired bans during our run-through, we need to regenerate the bans cache
        if (! empty($remove))
        {
            $db = $this->c->DB;
            $db->query('DELETE FROM '.$db->prefix.'bans WHERE id IN (' . implode(',', $remove) . ')') or error('Unable to delete expired ban', __FILE__, __LINE__, $db->error());
            $this->c->{'bans update'};
        }
        return $banned;
    }
}
