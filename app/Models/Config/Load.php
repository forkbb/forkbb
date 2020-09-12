<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;
use ForkBB\Models\Config\Model as Config;
use PDO;

class Load extends Method
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     */
    public function load(): Config
    {
        $query = 'SELECT cf.conf_name, cf.conf_value
            FROM ::config AS cf';

        $config = $this->c->DB->query($query)->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->model->setAttrs($config);
        $this->c->Cache->set('config', $config);

        return $this->model;
    }
}
