<?php

namespace ForkBB\Models\Stats;

use ForkBB\Models\Method;

class Load extends Method
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     *
     * @return Stats
     */
    public function load()
    {
        $total = $this->c->DB->query('SELECT COUNT(id)-1 FROM ::users WHERE group_id!=0')->fetchColumn();
        $last  = $this->c->DB->query('SELECT id, username FROM ::users WHERE group_id!=0 ORDER BY registered DESC LIMIT 1')->fetch();
        $this->model->userTotal = $total;
        $this->model->userLast  = $last;
        $this->c->Cache->set('stats', [
            'total' => $total,
            'last'  => $last,
        ]);
        return $this->model;
    }
}
