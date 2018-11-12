<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use PDO;

class AdminsIds extends Action
{
    /**
     * Загружает список id админов из БД
     *
     * @return array
     */
    public function adminsIds()
    {
        $vars = [
            ':gid' => $this->c->GROUP_ADMIN,
        ];
        $sql = 'SELECT u.id FROM ::users AS u WHERE u.group_id=?i:gid';

        return $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);
    }
}
