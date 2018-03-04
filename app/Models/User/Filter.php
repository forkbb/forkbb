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
        $fields  = $this->c->dbMap->users;
        $orderBy = [];
        $where   = ['u.id>1'];

        foreach ($order as $field => $dir) {
            if (! isset($fields[$field])) {
                throw new InvalidArgumentException('No sorting field found');
            }
            if ('ASC' !== $dir && 'DESC' !== $dir) {
                throw new InvalidArgumentException('The sorting order is not clear');
            }
            $orderBy[] = "u.{$field} {$dir}";
        }
        if (empty($orderBy)) {
            $orderBy = 'u.username ASC';
        } else {
            $orderBy = \implode(', ', $orderBy);
        }

        $vars = [];

        foreach ($filters as $field => $rule) {
            if (! isset($fields[$field])) {
                throw new InvalidArgumentException('No sorting field found');
            }
            switch ($rule[0]) {
                case '=':
                case '!=':
                    $where[] = "u.{$field}{$rule[0]}?{$fields[$field]}";
                    $vars[]  = $rule[1];
                    break;
                case 'LIKE':
                    $where[] = "u.{$field} LIKE ?{$fields[$field]}";
                    $vars[]  = \str_replace(['*', '_'], ['%', '\\_'], $rule[1]);
                    break;
                default:
                    throw new InvalidArgumentException('The condition is not clear');
            }
        }

        $where = \implode(' AND ', $where);

        $sql = "SELECT u.id
                FROM ::users AS u
                WHERE {$where}
                ORDER BY {$orderBy}";

        $ids = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);

        return $ids;
    }
}
