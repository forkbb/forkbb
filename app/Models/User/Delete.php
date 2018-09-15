<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет пользователя(ей)
     *
     * @param mixed ...$users
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(...$users)
    {
        if (empty($users)) {
            throw new InvalidArgumentException('No arguments, expected User(s)');
        }

        $ids = [];
        $moderators = [];
        foreach ($users as $user) {
            if (! $user instanceof User) {
                throw new InvalidArgumentException('Expected User');
            }
            if ($user->isGuest) {
                throw new RuntimeException('Guest can not be deleted');
            }

            $ids[] = $user->id;

            if ($user->isAdmMod) {
                $moderators[$user->id] = $user;
            }
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

        $this->c->forums->delete(...$users);

        //???? подписки, опросы, предупреждения

        foreach ($users as $user) {
            $this->c->Online->delete($user);
        }

        $vars = [
            ':users' => $ids,
        ];
        $sql = 'DELETE FROM ::users
                WHERE id IN (?ai:users)';
        $this->c->DB->exec($sql, $vars);
    }
}
