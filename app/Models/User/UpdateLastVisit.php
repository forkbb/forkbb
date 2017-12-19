<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class UpdateLastVisit extends Action
{
    /**
     * Обновляет время последнего визита пользователя
     * 
     * @param User $user
     *
     * @throws RuntimeException
     */
    public function updateLastVisit(User $user)
    {
        if ($user->id < 2) {
            throw new RuntimeException('Expected user');
        }
        if ($user->isLogged) {
            $this->c->DB->exec('UPDATE ::users SET last_visit=?i:loggid WHERE id=?i:id', [':loggid' => $user->logged, ':id' => $user->id]);
            $user->__last_visit = $user->logged;
        }
    }
}
