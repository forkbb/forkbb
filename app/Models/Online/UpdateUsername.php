<?php

declare(strict_types=1);

namespace ForkBB\Models\Online;

use ForkBB\Models\Method;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class UpdateUsername extends Method
{
    /**
     * Обновляет имя пользователя в таблице online
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
        $query = 'UPDATE ::online
            SET ident=?s:name
            WHERE user_id=?i:id';

        $this->c->DB->exec($query, $vars);
    }
}
