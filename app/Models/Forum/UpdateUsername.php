<?php

declare(strict_types=1);

namespace ForkBB\Models\Forum;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class UpdateUsername extends Action
{
    /**
     * Обновляет имя пользователя в списке разделов и модераторах
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

        $forums = $this->c->ForumManager->init($this->c->GROUP_ADMIN)->get(0)->descendants;

        foreach ($forums as $forum) {
            if ($user->isModerator($forum)) {
                $forum->modAdd($user); // переименование модератора

                $this->c->forums->update($forum);
            }
        }
    }
}
