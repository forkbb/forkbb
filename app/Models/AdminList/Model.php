<?php

namespace ForkBB\Models\AdminList;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    /**
     * Загружает список id админов из кеша/БД
     *
     * @return AdminList\Model
     */
    public function init(): self
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
     * @return AdminList\Model
     */
    public function reset(): self
    {
        $this->c->Cache->delete('admins');

        return $this;
    }
}
