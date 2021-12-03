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

class IsUniqueName extends Action
{
    /**
     * Проверка на уникальность имени пользователя
     */
    public function isUniqueName(User $user): bool
    {
        $vars = [
            ':id'    => (int) $user->id,
            ':name'  => $user->username,
            ':norm'  => $this->manager->normUsername($user->username),
            ':normL' => $this->manager->normUsername(\mb_strtolower($user->username, 'UTF-8')), // ????
            ':normU' => $this->manager->normUsername(\mb_strtoupper($user->username, 'UTF-8')), // ????
        ];
        $query = 'SELECT 1
            FROM ::users AS u
            WHERE u.id!=?i:id
                AND (
                    LOWER(u.username)=LOWER(?s:name)
                    OR u.username_normal=?s:norm
                    OR LOWER(u.username_normal)=?s:normL
                    OR UPPER(u.username_normal)=?s:normU
                )';

        $result = $this->c->DB->query($query, $vars)->fetchAll();

        return ! \count($result);
    }
}
