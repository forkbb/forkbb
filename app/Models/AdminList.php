<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;

class AdminList extends Model
{
    /**
     * Загружает список id админов из кеша/БД
     *
     * @return AdminList
     */
    public function init()
    {
        if ($this->c->Cache->has('admins')) {
            $this->list  = $this->c->Cache->get('admins');
        } else {
            $this->load();
        }
        return $this;
    }
}
