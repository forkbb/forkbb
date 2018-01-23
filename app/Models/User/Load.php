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
     * @return mixed
     */
    public function load($value, $field = 'id')
    {
        $flag = is_array($value);

        switch (($flag ? 'a_' : '') . $field) {
            case 'id':
                $where = 'u.id=?i:field';
                break;
            case 'username':
                $where = 'u.username=?s:field';
                break;
            case 'email':
                $where = 'u.email=?s:field';
                break;
            case 'a_id':
                $where = 'u.id IN (?ai:field)';
                break;
            case 'a_username':
                $where = 'u.username IN (?as:field)';
                break;
            case 'a_email':
                $where = 'u.email IN (?as:field)';
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

        if ($flag) {
            $result = [];
            foreach ($data as $row) {
                $result[] = $this->manager->create($row);
            }
            return $result;
        } else {
            $count = count($data);
            // число найденных пользователей отлично от одного или гость
            if (1 !== $count || 1 === $data[0]['id']) {
                return $count;
            }
            return $this->manager->create($data[0]);
        }
    }
}
