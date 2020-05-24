<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use ForkBB\Models\Group\Model as Group;
use RuntimeException;

class Promote extends Action
{
    /**
     * Обновляет данные пользователя
     *
     * @param mixed ...$args
     *
     * @throws RuntimeException
     *
     * @return int
     */
    public function promote(...$args): int
    {
        $count = \count($args);

        // перемещение всех пользователей из группы 0 в группу 1
        if (2 == $count && $args[0] instanceof Group && $args[1] instanceof Group) {
            $vars = [
                ':old' => $args[0]->g_id,
                ':new' => $args[1]->g_id,
            ];
            $sql = 'UPDATE ::users
                    SET group_id=?i:new
                    WHERE group_id=?i:old';
            return $this->c->DB->exec($sql, $vars);
        // продвижение всех пользователей в группе 0
        } elseif (1 == $count && $args[0] instanceof Group) {
            $vars = [
                ':old'   => $args[0]->g_id,
                ':new'   => $args[0]->g_promote_next_group,
                ':count' => $args[0]->g_promote_min_posts,
            ];
            $sql = 'UPDATE ::users
                    SET group_id=?i:new
                    WHERE group_id=?i:old AND num_posts>=?i:count';
            return $this->c->DB->exec($sql, $vars);
        } else {
            throw new InvalidArgumentException("Unexpected parameters type, Illegal number of parameters ({$count})");
        }
    }
}
