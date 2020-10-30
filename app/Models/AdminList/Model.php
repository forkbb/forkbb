<?php

declare(strict_types=1);

namespace ForkBB\Models\AdminList;

use ForkBB\Models\Model as ParentModel;
use RuntimeException;

class Model extends ParentModel
{
    /**
     * Загружает список id админов из кеша/БД
     * Создает кеш
     */
    public function init(): Model
    {
        $this->list = $this->c->Cache->get('admins');

        if (! \is_array($this->list)) {
            $this->list = \array_flip($this->c->users->adminsIds());

            if (true !== $this->c->Cache->set('admins', $this->list)) {
                throw new RuntimeException('Unable to write value to cache - admins');
            }
        }

        return $this;
    }

    /**
     * Сбрасывает кеш списка id админов
     */
    public function reset(): Model
    {
        if (true !== $this->c->Cache->delete('admins')) {
            throw new RuntimeException('Unable to remove key from cache - admins');
        }

        return $this;
    }
}
