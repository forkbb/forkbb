<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\PM;

use ForkBB\Models\Method;
use ForkBB\Models\User\User;
use RuntimeException;

class UpdateUsername extends Method
{
    /**
     * Обновляет имя пользователя в таблицах PM
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
        $query = 'UPDATE ::pm_posts
            SET poster=?s:name
            WHERE poster_id=?i:id';

        $this->c->DB->exec($query, $vars);

        $query = 'UPDATE ::pm_topics
            SET poster=?s:name
            WHERE poster_id=?i:id';

        $this->c->DB->exec($query, $vars);

        $query = 'UPDATE ::pm_topics
            SET target=?s:name
            WHERE target_id=?i:id';

        $this->c->DB->exec($query, $vars);
    }
}
