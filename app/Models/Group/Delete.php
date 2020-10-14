<?php

declare(strict_types=1);

namespace ForkBB\Models\Group;

use ForkBB\Models\Action;
use ForkBB\Models\Group\Model as Group;

class Delete extends Action
{
    /**
     * Удаляет группу
     */
    public function delete(Group $group, Group $new = null): void
    {
        if (null !== $new) {
            $this->c->users->promote($group, $new);
        }

        $this->manager->Perm->delete($group);

        $vars  = [
            ':gid' => $group->g_id,
        ];
        $query = 'DELETE
            FROM ::groups
            WHERE g_id=?i:gid';

        $this->c->DB->exec($query, $vars);
    }
}
