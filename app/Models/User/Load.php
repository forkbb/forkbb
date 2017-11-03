<?php

namespace ForkBB\Models\User;

use ForkBB\Models\MethodModel;
use InvalidArgumentException;

class Load extends MethodModel
{
    /**
     * Получение пользователя по условию
     * @param mixed $value
     * @param string $field
     * @throws InvalidArgumentException
     * @return int|User
     */
    public function load($value, $field = 'id')
    {
        switch ($field) {
            case 'id':
                $where = 'u.id= ?i';
                break;
            case 'username':
                $where = 'u.username= ?s';
                break;
            case 'email':
                $where = 'u.email= ?s';
                break;
            default:
                throw new InvalidArgumentException('Field not supported');
        }

        $data = $this->c->DB->query('SELECT u.*, g.* FROM ::users AS u LEFT JOIN ::groups AS g ON u.group_id=g.g_id WHERE ' . $where, [$value])
            ->fetchAll();

        // число найденных пользователей отлично от одного
        if (count($data) !== 1) {
            return count($data);
        }
        // найден гость
        if ($data[0]['id'] < 2) {
            return 1;
        }
        return $this->model->setAttrs($data[0]);
    }
}
