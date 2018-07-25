<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;
use PDO;

class Load extends Method
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     *
     * @return Config
     */
    public function load()
    {
        $config = $this->c->DB->query('SELECT cf.conf_name, cf.conf_value FROM ::config AS cf')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->model->setAttrs($config);
        $this->c->Cache->set('config', $config);
        return $this->model;
    }
}
