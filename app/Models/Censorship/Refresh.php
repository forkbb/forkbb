<?php

declare(strict_types=1);

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Method;

class Refresh extends Method
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     */
    public function refresh(): array
    {
        $query = 'SELECT ce.id, ce.search_for, ce.replace_with
            FROM ::censoring AS ce';

        $stmt    = $this->c->DB->query($query);
        $search  = [];
        $replace = [];
        while ($row = $stmt->fetch()) {
            $search[$row['id']]  = '%(?<![\p{L}\p{N}])('
                . \str_replace('\*', '[\p{L}\p{N}]*?', \preg_quote($row['search_for'], '%'))
                . ')(?![\p{L}\p{N}])%iu';
            $replace[$row['id']] = $row['replace_with'];
        }

        return [
            'searchList'  => $search,
            'replaceList' => $replace,
        ];
    }
}
