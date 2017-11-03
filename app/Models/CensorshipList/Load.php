<?php

namespace ForkBB\Models\CensorshipList;

use ForkBB\Models\MethodModel;

class Load extends MethodModel
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     *
     * @return CensorshipList
     */
    public function load()
    {

        $stmt = $this->c->DB->query('SELECT search_for, replace_with FROM ::censoring');
        $search  = [];
        $replace = [];
        while ($row = $stmt->fetch()) {
            $replace[] = $row['replace_with'];
            $search[]  = '%(?<![\p{L}\p{N}])('.str_replace('\*', '[\p{L}\p{N}]*?', preg_quote($row['search_for'], '%')).')(?![\p{L}\p{N}])%iu';
        }
        $this->model->searchList   = $search;
        $this->model->replaceList  = $replace;
        $this->c->Cache->set('censorship', [
            'searchList'  => $search,
            'replaceList' => $replace,
        ]);
        return $this->model;
    }
}
