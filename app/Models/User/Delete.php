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
use ForkBB\Models\User\User;
use ForkBB\Models\Forum\Forum;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет пользователя(ей)
     */
    public function delete(User ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s)');
        }

        $users      = [];
        $moderators = [];
        $resetAdmin = false;
        $resetBan   = false;

        foreach ($args as $user) {
            if ($user->isGuest) {
                throw new RuntimeException('Guest can not be deleted');
            }

            if ($user->isAdmMod) {
                $moderators[$user->id] = $user;
            }

            if ($user->isAdmin) {
                $resetAdmin = true;
            }

            $users[$user->id] = $user;
        }

        if (\count($users) > 1) {
            \ksort($users, \SORT_NUMERIC);
        }

        if ($moderators) {
            $forums = $this->c->ForumManager->init($this->c->groups->get(FORK_GROUP_ADMIN));
            $root   = $forums->get(0);

            if ($root instanceof Forum) {
                foreach ($root->descendants as $forum) {
                    $forum->modDelete(...$moderators);
                    $this->c->forums->update($forum);
                }
            }
        }

        $this->c->pms->delete(...$users);
        $this->c->subscriptions->unsubscribe(...$users);
        $this->c->forums->delete(...$users);
        $this->c->providerUser->delete(...$users);

        //???? предупреждения

        foreach ($users as $user) {
            $this->c->Online->delete($user);

            $user->deleteAvatar();

            // имя и email удаляемого пользователя в бан
            if (! $user->isBanByName) {
                $this->c->bans->insert([
                    'username' => $user->username,
                    'ip'       => '',
                    'email'    => $user->email,
                    'message'  => 'remote user',
                    'expire'   => 0,
                ]);

                $resetBan = true;
            }
        }

        $vars = [
            ':users' => \array_keys($users),
        ];
        $query = 'DELETE
            FROM ::users
            WHERE id IN (?ai:users)';

        $this->c->DB->exec($query, $vars);

        if ($resetAdmin) {
            $this->c->admins->reset();
        }

        if ($resetBan) {
            $this->c->bans->reset();
        }

        $this->c->stats->reset();
    }
}
