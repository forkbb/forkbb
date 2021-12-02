<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Online;

use ForkBB\Models\Method;
use ForkBB\Models\User\User;
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
