<?php

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use PDO;

class AdminsIds extends Action
{
    /**
     * Загружает список id админов из БД
     */
    public function adminsIds(): array
    {
        $vars  = [
            ':gid' => $this->c->GROUP_ADMIN,
        ];
        $query = 'SELECT u.id
            FROM ::users AS u
            WHERE u.group_id=?i:gid';

        return $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
    }
}
