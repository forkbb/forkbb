<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\User\User;
use RuntimeException;

class UpdateUsername extends Action
{
    /**
     * Обновляет имя пользователя в таблице сообщений
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
        $query = 'UPDATE ::posts
            SET poster=?s:name
            WHERE poster_id=?i:id';

        $this->c->DB->exec($query, $vars);

        $query = 'UPDATE ::posts
            SET editor=?s:name
            WHERE editor_id=?i:id';

        $this->c->DB->exec($query, $vars);
    }
}
