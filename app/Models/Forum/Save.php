<?php

namespace ForkBB\Models\Forum;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет раздел в БД
     *
     * @param Forum $forum
     * 
     * @throws RuntimeException
     * 
     * @return Forum
     */
    public function update(Forum $forum)
    {
        if ($forum->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }
        $modified = $forum->getModified();
        if (empty($modified)) {
            return $forum;
        }
        $values = $forum->getAttrs();
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
            return $forum;
        }
        $vars[] = $forum->id;
        $this->c->DB->query('UPDATE ::forums SET ' . implode(', ', $set) . ' WHERE id=?i', $vars);
        $forum->resModified();

        return $forum;
    }

    /**
     * Добавляет новый раздел в БД
     *
     * @param Forum $forum
     * 
     * @throws RuntimeException
     * 
     * @return int
     */
    public function insert(Forum $forum)
    {
        if (null !== $forum->id) {
            throw new RuntimeException('The model has ID');
        }
        $attrs  = $forum->getAttrs();
        $fileds = $this->c->dbMap->forums;
        $set = $set2 = $vars = [];
        foreach ($attrs as $key => $value) {
            if (! isset($fileds[$key])) {
                continue;
            }
            $vars[] = $value;
            $set[]  = $key;
            $set2[] = '?' . $fileds[$key];
        }
        if (empty($set)) {
            throw new RuntimeException('The model is empty');
        }
        $this->c->DB->query('INSERT INTO ::forums (' . implode(', ', $set) . ') VALUES (' . implode(', ', $set2) . ')', $vars);
        $forum->id = $this->c->DB->lastInsertId();
        $forum->resModified();

        return $forum->id;
    }
}
