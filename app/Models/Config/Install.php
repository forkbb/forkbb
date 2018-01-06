<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;

class Install extends Method
{
    /**
     * Заполняет модель данными
     *
     * @return Config
     */
    public function install()
    {
        $this->model->setAttrs($this->c->forConfig);
        return $this->model;
    }
}
