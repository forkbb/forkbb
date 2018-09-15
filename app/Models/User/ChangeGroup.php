<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use RuntimeException;

class ChangeGroup extends Action
{
    /**
     * Обновляет группу указанных пользователей
     *
     * @param int $newGroupId
     * @param array ...$users
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function changeGroup($newGroupId, ...$users)
    {
        $newGroup = $this->c->groups->get($newGroupId);
        if (null === $newGroup || $newGroup->groupGuest) {
            throw new InvalidArgumentException('Expected group number');
        }

        $ids = [];
        $moderators = [];
        foreach ($users as $user) {
            if (! $user instanceof User) {
                throw new InvalidArgumentException('Expected User');
            }
            if ($user->isGuest) {
                throw new RuntimeException('Guest can not change group');
            }

            if (1 != $newGroup->g_moderator && $user->isAdmMod) {
                $moderators[$user->id] = $user;
            }

            $ids[] = $user->id;
            $user->__group_id = $newGroupId;
        }

        if (! empty($moderators)) {
            $root = $this->c->forums->get(0);
            if ($root instanceof Forum) {
                foreach ($this->c->forums->depthList($root, 0) as $forum) {
                    $forum->modDelete(...$moderators);
                    $this->c->forums->update($forum);
                }
            }
        }

        $vars  = [
            ':new' => $newGroupId,
            ':ids' => $ids,
        ];
        $sql = 'UPDATE ::users AS u
                SET u.group_id = ?i:new
                WHERE u.id IN (?ai:ids)';
        $this->c->DB->exec($sql, $vars);
    }
}
