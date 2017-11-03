<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\MethodModel;

class Load extends MethodModel
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     *
     * @return Config
     */
    public function load()
    {
        $config = $this->c->DB->query('SELECT conf_name, conf_value FROM ::config')->fetchAll(\PDO::FETCH_KEY_PAIR);
        $this->model->setAttrs($config);
        $this->c->Cache->set('config', $config);
        return $this->model;
    }
}
