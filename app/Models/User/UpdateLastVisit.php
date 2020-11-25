<?php

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class UpdateLastVisit extends Action
{
    /**
     * Обновляет время последнего визита пользователя
     */
    public function updateLastVisit(User $user): void
    {
        if ($user->isGuest) {
            throw new RuntimeException('Expected user');
        }
        if ($user->logged > 0) {
            $vars = [
                ':loggid' => $user->logged,
                ':id'     => $user->id,
            ];
            $query = 'UPDATE ::users
                SET last_visit=?i:loggid
                WHERE id=?i:id';

            $this->c->DB->exec($query, $vars);
            $user->__last_visit = $user->logged;
        }
    }
}
