<?php

declare(strict_types=1);

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use ForkBB\Models\BanList\Model as BanList;
use RuntimeException;

class Load extends Method
{
    /**
     * Загружает список банов из БД
     * Создает кеш
     */
    public function load(): BanList
    {
        $userList  = [];
        $emailList = [];
        $ipList    = [];
        $banList   = [];

        $query = 'SELECT b.id, b.username, b.ip, b.email, b.message, b.expire
            FROM ::bans AS b';

        $stmt = $this->c->DB->query($query);
        while ($row = $stmt->fetch()) {
            $name = $this->model->trimToNull($row['username'], true);
            if (null !== $name) {
                $userList[$name] = $row['id'];
            }

            $email   = $this->model->trimToNull($row['email']);
            if (null !== $email) {
                $email = $this->c->NormEmail->normalize($email);
                $emailList[$email] = $row['id']; // ???? TODO если домен забанен, то email не добавлять
            }

            $ips = $this->model->trimToNull($row['ip']);
            if (null !== $ips) {
                foreach (\explode(' ', $ips) as $ip) {
                    $list    = &$ipList;
                    $letters = \str_split($this->model->ip2hex($ip));
                    $count   = \count($letters);

                    foreach ($letters as $letter) {
                        if (--$count) {
                            if (! isset($list[$letter])) {
                                $list[$letter] = [];
                            } elseif (! \is_array($list[$letter])) {
                                break;
                            }
                            $list = &$list[$letter];
                        } else {
                            $list[$letter] = $row['id']; // ???? может не перезаписывать предыдущий бан?
                        }
                    }

                    unset($list);
                }
            }

            $message = $this->model->trimToNull($row['message']);
            $expire  = empty($row['expire']) ? null : $row['expire'];

            if (
                null === $message
                && null === $expire
            ) {
                continue;
            }

            $banList[$row['id']] = [
                'message'  => $message,
                'expire'   => $expire,
            ];
        }
        $this->model->banList   = $banList;
        $this->model->userList  = $userList;
        $this->model->emailList = $emailList;
        $this->model->ipList    = $ipList;
        $result = $this->c->Cache->set('banlist', [
            'banList'   => $banList,
            'userList'  => $userList,
            'emailList' => $emailList,
            'ipList'    => $ipList,
        ]);

        if (true !== $result) {
            throw new RuntimeException('Unable to write value to cache - banlist');
        }

        return $this->model;
    }
}
