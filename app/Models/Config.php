<?php

namespace ForkBB\Models;

use ForkBB\Models\DataModel;

class Config extends DataModel
{
    /**
     * Заполняет модель данными из кеша/БД
     *
     * @return Config
     */
    public function init()
    {
        if ($this->c->Cache->has('config')) {
            $this->setAttrs($this->c->Cache->get('config'));
        } else {
            $this->load();
        }
        return $this;
    }
}
