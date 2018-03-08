<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Model as Topic;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет тему в БД
     *
     * @param Topic $topic
     *
     * @throws RuntimeException
     *
     * @return Topic
     */
    public function update(Topic $topic)
    {
        if ($topic->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }
        $modified = $topic->getModified();
        if (empty($modified)) {
            return $topic;
        }
        $values = $topic->getAttrs();
        $fileds = $this->c->dbMap->topics;
        $set = $vars = [];
        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }
            $vars[] = $values[$name];
            $set[] = $name . '=?' . $fileds[$name];
        }
        if (empty($set)) {
            return $topic;
        }
        $vars[] = $topic->id;
        $this->c->DB->query('UPDATE ::topics SET ' . \implode(', ', $set) . ' WHERE id=?i', $vars);
        $topic->resModified();

        return $topic;
    }

    /**
     * Добавляет новую тему в БД
     *
     * @param Topic $topic
     *
     * @throws RuntimeException
     *
     * @return int
     */
    public function insert(Topic $topic)
    {
        if (null !== $topic->id) {
            throw new RuntimeException('The model has ID');
        }
        $attrs  = $topic->getAttrs();
        $fileds = $this->c->dbMap->topics;
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
        $this->c->DB->query('INSERT INTO ::topics (' . \implode(', ', $set) . ') VALUES (' . \implode(', ', $set2) . ')', $vars);
        $topic->id = $this->c->DB->lastInsertId();
        $topic->resModified();

        return $topic->id;
    }
}
