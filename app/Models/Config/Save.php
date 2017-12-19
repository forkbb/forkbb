<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;

class Save extends Method
{
    /**
     * Сохраняет изменения модели в БД
     * Удаляет кеш
     *
     * @return Config
     */
    public function save()
    {
        $modified = $this->model->getModified();
        if (empty($modified)) {
            return;
        }

        $values = $this->model->getAttrs();
        foreach ($modified as $name) {
            $vars = [
                ':value' => $values[$name],
                ':name'  => $name
            ];
            //????
            //????
            $count = $this->c->DB->exec('UPDATE ::config SET conf_value=?s:value WHERE conf_name=?s:name', $vars);
            //????
            //????
            if ($count === 0) {
                //????
                //????
                $this->c->DB->exec('INSERT INTO ::config (conf_name, conf_value) SELECT ?s:name, ?s:value FROM ::groups WHERE NOT EXISTS (SELECT 1 FROM ::config WHERE conf_name=?s:name) LIMIT 1', $vars);
            }
        }
        $this->c->Cache->delete('config');
        return $this->model;
    }
}
