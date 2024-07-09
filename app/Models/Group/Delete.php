<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Group;

use ForkBB\Models\Action;
use ForkBB\Models\Group\Group;

class Delete extends Action
{
    /**
     * Удаляет группу
     */
    public function delete(Group $group, ?Group $new = null): void
    {
        if (null !== $new) {
            $this->c->users->promote($group, $new);
        }

        $this->manager->Perm->delete($group);

        $vars = [
            ':gid' => $group->g_id,
        ];
        $query = 'DELETE
            FROM ::groups
            WHERE g_id=?i:gid';

        $this->c->DB->exec($query, $vars);
    }
}
