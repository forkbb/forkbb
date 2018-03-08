<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;

class Load extends Method
{
    /**
     * Загружает список банов из БД
     * Создает кеш
     *
     * @return BanList
     */
    public function load()
    {
        $userList  = [];
        $ipList    = [];
        $otherList = [];
        $stmt = $this->c->DB->query('SELECT id, username, ip, email, message, expire FROM ::bans');
        while ($row = $stmt->fetch()) {
            $name = $this->model->trimToNull($row['username'], true);
            if (null !== $name) {
                $userList[$name] = $row['id'];
            }

            $ips = $this->model->trimToNull($row['ip']);
            if (null !== $ips) {
                foreach (\explode(' ', $ips) as $ip) {
                    $ip = \trim($ip);
                    if ($ip != '') {
                        $ipList[$ip] = $row['id'];
                    }
                }
            }

            $email   = $this->model->trimToNull($row['email']);
            $message = $this->model->trimToNull($row['message']);
            $expire  = empty($row['expire']) ? null : $row['expire'];
            if (! isset($email) && ! isset($message) && ! isset($expire)) {
                continue;
            }

            $otherList[$row['id']] = [
                'email'    => $email,
                'message'  => $message,
                'expire'   => $expire,
            ];
        }
        $this->model->otherList = $otherList;
        $this->model->userList  = $userList;
        $this->model->ipList    = $ipList;
        $this->c->Cache->set('banlist', [
            'otherList' => $otherList,
            'userList'  => $userList,
            'ipList'    => $ipList,
        ]);
        return $this->model;
    }
}
