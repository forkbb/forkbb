<?php

namespace ForkBB\Models\User;

use ForkBB\Models\MethodModel;
use RuntimeException;

class Save extends MethodModel
{
    /**
     * Обновляет данные пользователя
     *
     * @throws RuntimeException
     */
    public function save()
    {
        if (empty($this->model->id)) {
            throw new RuntimeException('The model does not have ID');
        }
        $modified = $this->model->getModified();
        if (empty($modified)) {
            return;
        }
        $values = $this->model->getAttrs();
        $fileds = $this->c->dbMap->users;
        $set = $vars = [];
        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }
            $vars[] = $values[$name];
            $set[] = $name . '=?' . $fileds[$name];
        }
        if (empty($set)) {
            return;
        }
        $vars[] = $this->model->id;
        $this->c->DB->query('UPDATE ::users SET ' . implode(', ', $set) . ' WHERE id=?i', $vars);
        $this->model->resModified();
    }

    /**
     * Добавляет новую запись в таблицу пользователей
     *
     * @throws RuntimeException
     */
    public function insert()
    {
        $modified = $this->model->getModified();
        if (null !== $this->model->id || in_array('id', $modified)) {
            throw new RuntimeException('The model has ID');
        }
        $values = $this->model->getAttrs();
        $fileds = $this->c->dbMap->users;
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
            return;
        }
        $this->c->DB->query('INSERT INTO ::users (' . implode(', ', $set) . ') VALUES (' . implode(', ', $set2) . ')', $vars);
        $this->model->resModified();
        return $this->c->DB->lastInsertId();
    }
}
