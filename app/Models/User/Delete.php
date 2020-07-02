<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Forum\Manager as ForumManager;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет пользователя(ей)
     *
     * @param User ...$users
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(User ...$users): void
    {
        if (empty($users)) {
            throw new InvalidArgumentException('No arguments, expected User(s)');
        }

        $ids          = [];
        $moderators   = [];
        $adminPresent = false;
        foreach ($users as $user) {
            if ($user->isGuest) {
                throw new RuntimeException('Guest can not be deleted');
            }

            $ids[] = $user->id;

            if ($user->isAdmMod) {
                $moderators[$user->id] = $user;
            }
            if ($user->isAdmin) {
                $adminPresent = true;
            }
        }

        if (! empty($moderators)) {
            $forums = new ForumManager($this->c);
            $forums->init($this->c->groups->get($this->c->GROUP_ADMIN));
            $root = $forums->get(0);

            if ($root instanceof Forum) {
                foreach ($root->descendants as $forum) {
                    $forum->modDelete(...$moderators);
                    $this->c->forums->update($forum);
                }
            }
        }

        $this->c->forums->delete(...$users);

        //???? подписки, опросы, предупреждения

        foreach ($users as $user) {
            $this->c->Online->delete($user);
        }

        $vars  = [
            ':users' => $ids,
        ];
        $query = 'DELETE
            FROM ::users
            WHERE id IN (?ai:users)';

        $this->c->DB->exec($query, $vars);

        if ($adminPresent) {
            $this->c->admins->reset();
        }
        $this->c->stats->reset();
    }
}
