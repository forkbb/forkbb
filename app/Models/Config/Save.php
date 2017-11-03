<?php

namespace ForkBB\Models\Config;

use ForkBB\Models\MethodModel;

class Save extends MethodModel
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
            $count = $this->c->DB->exec('UPDATE ::config SET conf_value=?s:value WHERE conf_name=?s:name', $vars);
            if ($count === 0) {
                $this->c->DB->exec('INSERT INTO ::config (conf_name, conf_value) VALUES (?s:name, ?s:value)', $vars);
            }
        }
        $this->c->Cache->delete('config');
        return $this->model;
    }
}
