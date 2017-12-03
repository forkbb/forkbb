<?php

namespace ForkBB\Models\Forum;

use ForkBB\Models\MethodModel;
use RuntimeException;

class Save extends MethodModel
{
    /**
     * Обновляет данные пользователя
     *
     * @throws RuntimeException
     * 
     * @return Forum
     */
    public function update()
    {
        if (empty($this->model->id)) {
            throw new RuntimeException('The model does not have ID');
        }
        $modified = $this->model->getModified();
        if (empty($modified)) {
            return $this->model;
        }
        $values = $this->model->getAttrs();
        $fileds = $this->c->dbMap->forums;
        $set = $vars = [];
        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }
            $vars[] = $values[$name];
            $set[] = $name . '=?' . $fileds[$name];
        }
        if (empty($set)) {
            return $this->model;
        }
        $vars[] = $this->model->id;
        $this->c->DB->query('UPDATE ::forums SET ' . implode(', ', $set) . ' WHERE id=?i', $vars);
        $this->model->resModified();

        return $this->model;
    }

    /**
     * Добавляет новую запись в таблицу пользователей
     *
     * @throws RuntimeException
     * 
     * @return int
     */
    public function insert()
    {
        $modified = $this->model->getModified();
        if (null !== $this->model->id || in_array('id', $modified)) {
            throw new RuntimeException('The model has ID');
        }
        $values = $this->model->getAttrs();
        $fileds = $this->c->dbMap->forums;
        $set = $set2 = $vars = [];
        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }
            $vars[] = $values[$name];
            $set[] = $name;
            $set2[] = '?' . $fileds[$name];
        }
        if (empty($set)) {
            throw new RuntimeException('The model is empty');
        }
        $this->c->DB->query('INSERT INTO ::forums (' . implode(', ', $set) . ') VALUES (' . implode(', ', $set2) . ')', $vars);
        $this->model->id = $this->c->DB->lastInsertId();
        $this->model->resModified();

        return $this->model->id;
    }
}
