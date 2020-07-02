<?php

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Method;
use ForkBB\Models\Censorship\Model as Censorship;

class Refresh extends Method
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     *
     * @return Censorship
     */
    public function refresh(): Censorship
    {
        $stmt    = $this->c->DB->query('SELECT ce.id, ce.search_for, ce.replace_with FROM ::censoring AS ce');
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
        $this->c->Cache->set('censorship', [
            'searchList'  => $search,
            'replaceList' => $replace,
        ]);
        return $this->model;
    }
}
