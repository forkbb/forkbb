<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use InvalidArgumentException;

class Load extends Action
{
    /**
     * Получение пользователя по условию
     * 
     * @param mixed $value
     * @param string $field
     * 
     * @throws InvalidArgumentException
     * 
     * @return int|User
     */
    public function load($value, $field = 'id')
    {
        switch ($field) {
            case 'id':
                $where = 'u.id=?i:field';
                break;
            case 'username':
                $where = 'u.username=?s:field';
                break;
            case 'email':
                $where = 'u.email=?s:field';
                break;
            default:
                throw new InvalidArgumentException('Field not supported');
        }
        $vars = [':field' => $value];
        $sql = 'SELECT u.*, g.* 
                FROM ::users AS u 
                LEFT JOIN ::groups AS g ON u.group_id=g.g_id 
                WHERE ' . $where;

        $data = $this->c->DB->query($sql, $vars)->fetchAll();

        // число найденных пользователей отлично от одного или гость
        $count = count($data);
        if (1 !== $count || 1 === $data[0]['id']) {
            return $count;
        }

        return $this->manager->create($data[0]);
    }
}
