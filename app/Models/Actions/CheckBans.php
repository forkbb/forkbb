<?php

namespace ForkBB\Models\Actions;

use ForkBB\Models\User;
use R2\DependencyInjection\ContainerInterface;

class CheckBans
{
    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    public function __construct(ContainerInterface $container)
    {
        $this->c = $container;
    }

    /**
     * Возвращает массив с описанием бана или null
     * @param User $user
     *
     * @return null|array
     */
    public function check(User $user) //????
    {
        $bans = $this->c->get('bans');

        // Для админов и при отсутствии банов прекращаем проверку
        if ($user['g_id'] == PUN_ADMIN || empty($bans)) {
           return null;
        }

        // Add a dot or a colon (depending on IPv4/IPv6) at the end of the IP address to prevent banned address
        // 192.168.0.5 from matching e.g. 192.168.0.50
        $user_ip = get_remote_address();
        $add = strpos($user_ip, '.') !== false ? '.' : ':';
        $user_ip .= $add;

        $username = mb_strtolower($user['username']);

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

            if (! $user['is_guest']) {
                if ($cur['username'] != '' && $username == mb_strtolower($cur['username'])) {
                    $banned = $cur;
                    continue;
                } elseif ($cur['email'] != '' && $user['email'] == $cur['email']) {
                    $banned = $cur;
                    continue;
                }
            }

            if ($cur['ip'] != '')
            {
                $ips = explode(' ', $cur['ip']);
                foreach ($ips as $ip) {
                    $ip .= $add;
                    if (substr($user_ip, 0, strlen($ip)) == $ip) {
                        $banned = $cur;
                        break;
                    }
                }
            }
        }

        // If we removed any expired bans during our run-through, we need to regenerate the bans cache
        if (! empty($remove))
        {
            $db = $this->c->get('DB');
            $db->query('DELETE FROM '.$db->prefix.'bans WHERE id IN (' . implode(',', $remove) . ')') or error('Unable to delete expired ban', __FILE__, __LINE__, $db->error());
            $this->c->get('bans update');
        }

        if ($banned)
        {
            //???? а зачем это надо?
            $this->c->get('Online')->delete($user);
            return $banned;
        }

        return null;
    }
}
