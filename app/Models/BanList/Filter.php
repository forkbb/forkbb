<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use InvalidArgumentException;
use PDO;

class Filter extends Method
{
    /**
     * Получение списка id банов по условиям
     */
    public function filter(array $filters, array $order = []): array
    {
        $fields  = $this->c->dbMap->bans;
        $orderBy = [];
        $where   = [];

        foreach ($order as $field => $dir) {
            if (! isset($fields[$field])) {
                throw new InvalidArgumentException("The '{$field}' field is not found");
            }
            if (
                'ASC' !== $dir
                && 'DESC' !== $dir
            ) {
                throw new InvalidArgumentException('The sort direction is not defined');
            }
            $orderBy[] = "b.{$field} {$dir}";
        }
        if (empty($orderBy)) {
            $orderBy = 'b.id DESC';
        } else {
            $orderBy = \implode(', ', $orderBy);
        }

        $vars = [];
        $like = 'pgsql' === $this->c->DB->getType() ? 'ILIKE' : 'LIKE';

        foreach ($filters as $field => $rule) {
            if (! isset($fields[$field])) {
                throw new InvalidArgumentException("The '{$field}' field is not found");
            }
            switch ($rule[0]) {
                case 'LIKE':
                    if (
                        false !== \strpos($rule[1], '*')
                        || 'ILIKE' === $like
                    ) {
                        // кроме * есть другие символы
                        if ('' != \trim($rule[1], '*')) {
                            $where[] = "b.{$field} {$like} ?{$fields[$field]} ESCAPE '#'";
                            $vars[]  = \str_replace(['#', '%', '_', '*'], ['##', '#%', '#_', '%'], $rule[1]);
                        }
                        break;
                    }
                    $rule[0] = '=';
                case '=':
                case '!=':
                    $where[] = "b.{$field}{$rule[0]}?{$fields[$field]}";
                    $vars[]  = $rule[1];
                    break;
                case 'BETWEEN':
                    // если и min, и max
                    if (isset($rule[1], $rule[2])) {
                        // min меньше max
                        if ($rule[1] < $rule[2]) {
                            $where[] = "b.{$field} BETWEEN ?{$fields[$field]} AND ?{$fields[$field]}";
                            $vars[]  = $rule[1];
                            $vars[]  = $rule[2];
                        // min больше max O_o
                        } elseif ($rule[1] > $rule[2]) {
                            $where[] = "b.{$field} NOT BETWEEN ?{$fields[$field]} AND ?{$fields[$field]}";
                            $vars[]  = $rule[1];
                            $vars[]  = $rule[2];
                        // min равен max :)
                        } else {
                            $where[] = "b.{$field}=?{$fields[$field]}";
                            $vars[]  = $rule[1];
                        }
                    // есть только min
                    } elseif (isset($rule[1])) {
                        $where[] = "b.{$field}>=?{$fields[$field]}";
                        $vars[]  = $rule[1];
                    // есть только max
                    } elseif (isset($rule[2])) {
                        $where[] = "b.{$field}<=?{$fields[$field]}";
                        $vars[]  = $rule[2];
                    }
                    break;
                default:
                    throw new InvalidArgumentException('The condition is not defined');
            }
        }

        if (empty($where)) {
            $query = "SELECT b.id
                FROM ::bans AS b
                ORDER BY {$orderBy}";
        } else {
            $where = \implode(' AND ', $where);
            $query = "SELECT b.id
                FROM ::bans AS b
                WHERE {$where}
                ORDER BY {$orderBy}";
        }

        return $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
    }
}
