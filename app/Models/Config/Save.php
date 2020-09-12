<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;
use ForkBB\Models\Config\Model as Config;

class Save extends Method
{
    /**
     * Сохраняет изменения модели в БД
     * Удаляет кеш
     */
    public function save(): Config
    {
        $modified = $this->model->getModified();
        if (empty($modified)) {
            return $this->model;
        }

        $values = $this->model->getAttrs();
        foreach ($modified as $name) {
            if (\array_key_exists($name, $values)) {
                $vars = [
                    ':value' => $values[$name],
                    ':name'  => $name
                ];
                //????
                //????
                $query = 'UPDATE ::config
                    SET conf_value=?s:value
                    WHERE conf_name=?s:name';

                $count = $this->c->DB->exec($query, $vars);
                //????
                //????
                if (0 === $count) {
                    //????
                    //????
                    $query = 'INSERT INTO ::config (conf_name, conf_value)
                        SELECT ?s:name, ?s:value
                        FROM ::groups
                        WHERE NOT EXISTS (
                            SELECT 1
                            FROM ::config
                            WHERE conf_name=?s:name
                        )
                        LIMIT 1';

                    $this->c->DB->exec($query, $vars);
                }
            } else {
                $vars = [
                    ':name'  => $name
                ];
                $query = 'DELETE FROM ::config
                    WHERE conf_name=?s:name';

                $this->c->DB->exec($query, $vars);
            }
        }
        $this->c->Cache->delete('config');

        return $this->model;
    }
}
