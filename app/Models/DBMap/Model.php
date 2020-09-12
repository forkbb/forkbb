<?php

namespace ForkBB\Models\DBMap;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    /**
     * Загружает карту БД из кеша/БД
     */
    public function init(): Model
    {
        if ($this->c->Cache->has('db_map')) {
            $this->setAttrs($this->c->Cache->get('db_map'));
        } else {
            $map = $this->c->DB->getMap();
            $this->c->Cache->set('db_map', $map);
            $this->setAttrs($map);
        }

        return $this;
    }

    /**
     * Сбрасывает кеш карты БД
     */
    public function reset(): Model
    {
        $this->c->Cache->delete('db_map');

        return $this;
    }
}
