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
        $config = [];
        $query  = 'SELECT cf.conf_name, cf.conf_value
            FROM ::config AS cf';

        $stmt = $this->c->DB->query($query);
        while ($row = $stmt->fetch()) {
            switch ($row['conf_name'][0]) {
                case 'a':
                    $value = \json_decode($row['conf_value'], true, 512, \JSON_THROW_ON_ERROR);
                    break;
                case 'i':
                    $value = (int) $row['conf_value'];
                    break;
                default:
                    $value = $row['conf_value'];
                    break;
            }

            $config[$row['conf_name']] = $value;
        }

        $this->model->setAttrs($config);
        $this->c->Cache->set('config', $config);

        return $this->model;
    }
}
