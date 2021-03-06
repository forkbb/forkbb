<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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
        $query = 'SELECT COUNT(u.id)-1
            FROM ::users AS u
            WHERE u.group_id!=0';

        $total = $this->c->DB->query($query)->fetchColumn();

        $query = 'SELECT u.id, u.username
            FROM ::users AS u
            WHERE u.group_id!=0
            ORDER BY u.registered DESC
            LIMIT 1';

        $last  = $this->c->DB->query($query)->fetch();

        return [
            'total' => $total,
            'last'  => $last,
        ];
    }
}
