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
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\User\User;
use InvalidArgumentException;
use RuntimeException;

class ChangeGroup extends Action
{
    /**
     * Обновляет группу указанных пользователей
     */
    public function changeGroup(int $newGroupId, User ...$users): void
    {
        $newGroup = $this->c->groups->get($newGroupId);

        if (
            null === $newGroup
            || $newGroup->groupGuest
        ) {
            throw new InvalidArgumentException('Expected group number');
        }

        $ids          = [];
        $moderators   = [];
        $adminPresent = $newGroup->groupAdmin;
        $unverPresent = false;

        foreach ($users as $user) {
            if ($user->isGuest) {
                throw new RuntimeException('Guest can not change group');
            }

            if (
                1 !== $newGroup->g_moderator
                && $user->isAdmMod
            ) {
                $moderators[$user->id] = $user;
            }

            if ($user->isAdmin) {
                $adminPresent = true;
            }

            if ($user->isUnverified) {
                $unverPresent = true;
            }

            $ids[]            = $user->id;
            $user->__group_id = $newGroupId;
        }

        if (! empty($moderators)) {
            $root = $this->c->forums->get(0); //???? вызов от группы админов?

            if ($root instanceof Forum) {
                foreach ($this->c->forums->depthList($root, 0) as $forum) {
                    $forum->modDelete(...$moderators);
                    $this->c->forums->update($forum);
                }
            }
        }

        if (\count($ids) > 1) {
            \sort($ids, \SORT_NUMERIC);
        }

        $vars = [
            ':new' => $newGroupId,
            ':ids' => $ids,
        ];
        $query = 'UPDATE ::users
            SET group_id = ?i:new
            WHERE id IN (?ai:ids)';

        $this->c->DB->exec($query, $vars);

        if ($adminPresent) {
            $this->c->admins->reset();
        }
        if ($unverPresent) {
            $this->c->stats->reset();
        }
    }
}
