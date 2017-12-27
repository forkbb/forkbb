<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;

class IsUniqueName extends Action
{
    /**
     * Проверка на уникальность имени пользователя
     * 
     * @param User $user
     * 
     * @return bool
     */
    public function isUniqueName(User $user)
    {
        $vars = [
            ':name' => $user->username,
            ':other' => preg_replace('%[^\p{L}\p{N}]%u', '', $user->username), //????
        ];
        $result = $this->c->DB->query('SELECT username FROM ::users WHERE LOWER(username)=LOWER(?s:name) OR LOWER(username)=LOWER(?s:other)', $vars)->fetchAll();
        return ! count($result);
    }
}
