<?php

declare(strict_types=1);

namespace ForkBB\Models\Config;

use ForkBB\Models\DataModel;

class Model extends DataModel
{
    /**
     * Заполняет модель данными из кеша/БД
     */
    public function init(): Model
    {
        $config = $this->c->Cache->get('config');

        if (\is_array($config)) {
            $this->setAttrs($config);
        } else {
            $this->load();
        }

        return $this;
    }
}
