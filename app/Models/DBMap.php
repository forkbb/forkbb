<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;

class DBMap extends Model
{
    /**
     * Загружает карту БД из кеша/БД
     *
     * @return DBMap
     */
    public function init()
    {
        if ($this->c->Cache->has('db_map')) {
            $this->a = $this->c->Cache->get('db_map');
        } else {
            $map = $this->c->DB->getMap();
            $this->c->Cache->set('db_map', $map);
            $this->a = $map;
        }
        return $this;
    }
}
