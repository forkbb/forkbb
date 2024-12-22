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
use ForkBB\Models\Group\Group;
use RuntimeException;

class Promote extends Action
{
    /**
     * Обновляет данные пользователя
     */
    public function promote(Group ...$args): int
    {
        $count = \count($args);

        // перемещение всех пользователей из группы 0 в группу 1
        if (2 == $count) {
            $vars = [
                ':old' => $args[0]->g_id,
                ':new' => $args[1]->g_id,
            ];
            $query = 'UPDATE ::users
                SET group_id=?i:new
                WHERE group_id=?i:old';

            return $this->c->DB->exec($query, $vars);

        // продвижение всех пользователей в группе 0
        } elseif (1 == $count) {
            $vars = [
                ':old'   => $args[0]->g_id,
                ':new'   => $args[0]->g_promote_next_group,
                ':count' => $args[0]->g_promote_min_posts,
            ];
            $query = 'UPDATE ::users
                SET group_id=?i:new
                WHERE group_id=?i:old AND num_posts>=?i:count';

            return $this->c->DB->exec($query, $vars);

        } else {
            throw new RuntimeException("Illegal number of parameters ({$count})");
        }
    }
}
