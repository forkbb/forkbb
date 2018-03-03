<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use InvalidArgumentException;
use PDO;

class Filter extends Action
{
    /**
     * Получение списка id пользователей по условиям
     *
     * @param array $filters
     * @param array $order
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function filter(array $filters, array $order = [])
    {
        $fileds  = $this->c->dbMap->users;
        $orderBy = [];
        $where   = ['u.id>1'];

        foreach ($order as $filed => $val) {
            if (! isset($fileds[$filed])) {
                throw new InvalidArgumentException('No sorting field found');
            }
            if ('ACS' !== $val && 'DESC' !== $val) {
                throw new InvalidArgumentException('The sorting order is not clear');
            }
            $orderBy[] = "u.{$filed} {$val}";
        }
        if (empty($orderBy)) {
            $orderBy = 'u.username ASC';
        } else {
            $orderBy = \implode(', ', $orderBy);
        }

        $where = \implode(' AND ', $where);

        $vars = [];
        $sql = "SELECT u.id
                FROM ::users AS u
                WHERE {$where}
                ORDER BY {$orderBy}";

        $ids = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);

        return $ids;
    }
}
