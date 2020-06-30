<?php

namespace ForkBB\Models\Group;

use ForkBB\Models\Action;
use ForkBB\Models\Group\Model as Group;

class Delete extends Action
{
    /**
     * Удаляет группу
     *
     * @param Group $group
     * @param Group $new
     */
    public function delete(Group $group, Group $new = null): void
    {
        if (null !== $new) {
            $this->c->users->promote($group, $new);
        }

        $this->manager->Perm->delete($group);

        $vars = [
            ':gid' => $group->g_id,
        ];
        $sql = 'DELETE FROM ::groups
                WHERE g_id=?i:gid';
        $this->c->DB->exec($sql, $vars);
    }
}
