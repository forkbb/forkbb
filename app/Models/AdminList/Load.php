<?php

namespace ForkBB\Models\AdminList;

use ForkBB\Models\MethodModel;

class Load extends MethodModel
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     *
     * @return AdminList
     */
    public function load()
    {
        $list = $this->c->DB->query('SELECT id FROM ::users WHERE group_id=?i', [$this->c->GROUP_ADMIN])->fetchAll(\PDO::FETCH_COLUMN);
        $this->model->list = $list;
        $this->c->Cache->set('admins', $list);
        return $this->model;
    }
}
