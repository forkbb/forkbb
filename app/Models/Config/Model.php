<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\DataModel;

class Model extends DataModel
{
    /**
     * Заполняет модель данными из кеша/БД
     */
    public function init(): Model
    {
        if ($this->c->Cache->has('config')) {
            $this->setAttrs($this->c->Cache->get('config'));
        } else {
            $this->load();
        }

        return $this;
    }
}
