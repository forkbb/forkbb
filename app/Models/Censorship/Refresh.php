<?php

declare(strict_types=1);

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Method;
use ForkBB\Models\Censorship\Model as Censorship;
use RuntimeException;

class Refresh extends Method
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     */
    public function refresh(): Censorship
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
        $this->model->searchList  = $search;
        $this->model->replaceList = $replace;
        $result = $this->c->Cache->set('censorship', [
            'searchList'  => $search,
            'replaceList' => $replace,
        ]);

        if (true !== $result) {
            throw new RuntimeException('Unable to write value to cache - censorship');
        }

        return $this->model;
    }
}
