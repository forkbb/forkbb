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
