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

class Stats extends Action
{
    /**
     * Возвращает данные по статистике пользователей
     */
    public function stats(): array
    {
        $vars = [
            ':gid' => FORK_GROUP_UNVERIFIED,
        ];
        $query = 'SELECT COUNT(u.id)
            FROM ::users AS u
            WHERE u.group_id!=?i:gid';

        $total = (int) $this->c->DB->query($query, $vars)->fetchColumn();

        $query = 'SELECT u.id, u.username
            FROM ::users AS u
            WHERE u.group_id!=?i:gid
            ORDER BY u.registered DESC
            LIMIT 1';

        $last  = $this->c->DB->query($query, $vars)->fetch();

        return [
            'total' => $total,
            'last'  => $last,
        ];
    }
}
