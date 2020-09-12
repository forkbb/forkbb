<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;
use ForkBB\Models\Config\Model as Config;

class Install extends Method
{
    /**
     * Заполняет модель данными
     */
    public function install(): Config
    {
        $this->model->setAttrs($this->c->forConfig);

        return $this->model;
    }
}
