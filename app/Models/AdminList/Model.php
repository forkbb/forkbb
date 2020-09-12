<?php

namespace ForkBB\Models\AdminList;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    /**
     * Загружает список id админов из кеша/БД
     */
    public function init(): Model
    {
        if ($this->c->Cache->has('admins')) {
            $this->list = $this->c->Cache->get('admins');
        } else {
            $this->list = \array_flip($this->c->users->adminsIds());
            $this->c->Cache->set('admins', $this->list);
        }

        return $this;
    }

    /**
     * Сбрасывает кеш списка id админов
     */
    public function reset(): Model
    {
        $this->c->Cache->delete('admins');

        return $this;
    }
}
