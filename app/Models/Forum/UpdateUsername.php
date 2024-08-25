<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Forum;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\User\User;
use RuntimeException;

class UpdateUsername extends Action
{
    /**
     * Обновляет имя пользователя в таблице разделов
     */
    public function updateUsername(User $user): void
    {
        if ($user->isGuest) {
            throw new RuntimeException('User expected, not guest');
        }

        $vars = [
            ':id'   => $user->id,
            ':name' => $user->username,
        ];
        $query = 'UPDATE ::forums
            SET last_poster=?s:name
            WHERE last_poster_id=?i:id';

        $this->c->DB->exec($query, $vars);

        $forums = $this->c->ForumManager->init($this->c->groups->get(FORK_GROUP_ADMIN))->get(0)->descendants;
        $isMod  = false;

        foreach ($forums as $forum) {
            if ($user->isModerator($forum)) {
                $isMod = true;

                $forum->modAdd($user); // переименование модератора
                $this->manager->update($forum);
            }
        }

        if ($isMod) {
            $this->manager->reset();
        }
    }
}
