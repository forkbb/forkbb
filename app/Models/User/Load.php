<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;

class Load extends Action
{
    /**
     * Получение пользователя
     *
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     *
     * @return mixed
     */
    public function load($value)
    {
        if (\is_array($value)) {
            $where = 'u.id IN (?ai:field)';
        } elseif ($value instanceof User) {
            if ('' != $value->username) {
                if (true === $value->ciNameSearch) {
                    $where = 'LOWER(u.username)=LOWER(?s:field)';
                } else {
                    $where = 'u.username=?s:field';
                }
                $value = $value->username;
            } elseif ('' != $value->email && '' != $value->email_normal) {
                $where = 'u.email_normal=?s:field';
                $value = $value->email_normal;
            } else {
                throw new InvalidArgumentException('Field not supported');
            }
        } else {
            $where = 'u.id=?i:field';
        }

        $vars = [':field' => $value];
        $sql = 'SELECT u.*, g.*
                FROM ::users AS u
                LEFT JOIN ::groups AS g ON u.group_id=g.g_id
                WHERE ' . $where;

        $data = $this->c->DB->query($sql, $vars)->fetchAll();

        $result = [];
        foreach ($data as $row) {
            $result[] = $this->manager->create($row);
        }
        return $result;
    }
}
