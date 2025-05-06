<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

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
        $vars = [
            ':gid' => FORK_GROUP_ADMIN,
        ];
        $query = 'SELECT u.id
            FROM ::users AS u
            WHERE u.group_id=?i:gid';

        return $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
    }
}
