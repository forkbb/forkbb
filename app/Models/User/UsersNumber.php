<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\Group\Model as Group;

class UsersNumber extends Action
{
    /**
     * Подсчет количества пользователей в группе
     * 
     * @param Group $group
     * 
     * @return int
     */
    public function UsersNumber(Group $group)
    {
        if (empty($group->g_id) || $group->g_id === $this->c->GROUP_GUEST) {
            return 0;
        }

        $vars = [
            ':gid' => $group->g_id,
        ];
        $sql = 'SELECT COUNT(id) FROM ::users WHERE group_id=?i:gid';

        return $this->c->DB->query($sql, $vars)->fetchColumn();
    }
}
