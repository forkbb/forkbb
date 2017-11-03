<?php

namespace ForkBB\Models\User;

use ForkBB\Models\MethodModel;

class IsUnique extends MethodModel
{
    /**
     * Проверка на уникальность имени пользователя
     * @param string $username
     * @return bool
     */
    public function isUnique($username = null)
    {
        if (null === $username) {
            $username = $this->model->username;
        }
        $vars = [
            ':name' => $username,
            ':other' => preg_replace('%[^\p{L}\p{N}]%u', '', $username),
        ];
        $result = $this->c->DB->query('SELECT username FROM ::users WHERE UPPER(username)=UPPER(?s:name) OR UPPER(username)=UPPER(?s:other)', $vars)->fetchAll();
        return ! count($result);
    }
}
