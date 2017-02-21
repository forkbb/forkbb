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
        $bans = $this->c->bans;
        $user = $this->c->user;

        // Для админов и при отсутствии банов прекращаем проверку
        if ($user->isAdmin || empty($bans)) {
           return null;
        }

        // Add a dot or a colon (depending on IPv4/IPv6) at the end of the IP address to prevent banned address
        // 192.168.0.5 from matching e.g. 192.168.0.50
        $userIp = $user->ip;
        $add = strpos($userIp, '.') !== false ? '.' : ':';
        $userIp .= $add;

        $username = mb_strtolower($user->username);

        $banned = false;
        $remove = [];

        foreach ($bans as $cur)
        {
            // Has this ban expired?
            if ($cur['expire'] != '' && $cur['expire'] <= time())
            {
                $remove[] = $cur['id'];
                continue;
            } elseif ($banned) {
                continue;
            }

            if (! $user->isGuest) {
                if ($cur['username'] != '' && $username == mb_strtolower($cur['username'])) {
                    $banned = $cur;
                    continue;
                } elseif ($cur['email'] != '' && $user->email == $cur['email']) {
                    $banned = $cur;
                    continue;
                }
            }

            if ($cur['ip'] != '')
            {
                $ips = explode(' ', $cur['ip']);
                foreach ($ips as $ip) {
                    $ip .= $add;
                    if (substr($userIp, 0, strlen($ip)) == $ip) {
                        $banned = $cur;
                        break;
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

        if ($banned)
        {
            //???? а зачем это надо?
            $this->c->Online->delete($user);
            return $banned;
        }

        return null;
    }
}
