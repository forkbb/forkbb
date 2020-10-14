<?php

declare(strict_types=1);

namespace ForkBB\Models\DBMap;

use ForkBB\Models\Model as ParentModel;
use RuntimeException;

class Model extends ParentModel
{
    /**
     * Загружает карту БД из кеша/БД
     */
    public function init(): Model
    {
        $map = $this->c->Cache->get('db_map');

        if (! \is_array($map)) {
            $map = $this->c->DB->getMap();

            if (true !== $this->c->Cache->set('db_map', $map)) {
                throw new RuntimeException('Unable to write value to cache - db_map');
            }
        }

        $this->setAttrs($map);

        return $this;
    }

    /**
     * Сбрасывает кеш карты БД
     */
    public function reset(): Model
    {
        if (true !== $this->c->Cache->delete('db_map')) {
            throw new RuntimeException('Unable to remove key from cache - db_map');
        }

        return $this;
    }
}
