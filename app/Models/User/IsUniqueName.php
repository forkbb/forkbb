<?php

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
            ':other' => \preg_replace('%[^\p{L}\p{N}]%u', '', $user->username), //???? что за бред :)
        ];
        $query = 'SELECT u.username
            FROM ::users AS u
            WHERE (LOWER(u.username)=LOWER(?s:name) OR LOWER(u.username)=LOWER(?s:other)) AND u.id!=?i:id';

        $result = $this->c->DB->query($query, $vars)->fetchAll();

        return ! \count($result);
    }
}
