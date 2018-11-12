<?php

namespace ForkBB\Models\AdminList;

use ForkBB\Models\Model as BaseModel;

class Model extends BaseModel
{
    /**
     * Загружает список id админов из кеша/БД
     *
     * @return Models\AdminList
     */
    public function init()
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
     *
     * @return Models\AdminList
     */
    public function reset()
    {
        $this->c->Cache->delete('admins');
        return $this;
    }
}
