<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;
use ForkBB\Models\Config\Model;

class Install extends Method
{
    /**
     * Заполняет модель данными
     *
     * @return Config
     */
    public function install(): Model
    {
        $this->model->setAttrs($this->c->forConfig);
        return $this->model;
    }
}
