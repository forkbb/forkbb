<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;

class Stats extends Action
{
    /**
     * Возвращает данные по статистике пользователей
     *
     * @return array
     */
    public function stats()
    {
        $total = $this->c->DB->query('SELECT COUNT(u.id)-1 FROM ::users AS u WHERE u.group_id!=0')->fetchColumn();
        $last  = $this->c->DB->query('SELECT u.id, u.username FROM ::users AS u WHERE u.group_id!=0 ORDER BY u.registered DESC LIMIT 1')->fetch();

        return [
            'total' => $total,
            'last'  => $last,
        ];
    }
}
