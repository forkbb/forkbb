<?php

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;

class IsUniqueName extends Action
{
    /**
     * Проверка на уникальность имени пользователя
     */
    public function isUniqueName(User $user): bool
    {
        $vars  = [
            ':id'    => (int) $user->id,
            ':name'  => $user->username,
        ];
        $query = 'SELECT u.username
            FROM ::users AS u
            WHERE LOWER(u.username)=LOWER(?s:name) AND u.id!=?i:id';

        $result = $this->c->DB->query($query, $vars)->fetchAll();

        return ! \count($result);

        // ???? нужен нормализованный username для определения уникальности
        // это https://www.unicode.org/Public/security/latest/confusables.txt преобразование не очень :(
    }
}
