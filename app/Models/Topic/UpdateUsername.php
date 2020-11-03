<?php

declare(strict_types=1);

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class UpdateUsername extends Action
{
    /**
     * Обновляет имя пользователя в таблице тем
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
        $query = 'UPDATE ::topics
            SET poster=?s:name
            WHERE poster_id=?i:id';

        $this->c->DB->exec($query, $vars);

        $query = 'UPDATE ::topics
            SET last_poster=?s:name
            WHERE last_poster_id=?i:id';

        $this->c->DB->exec($query, $vars);
    }
}
