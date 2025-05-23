<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Topic;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет тему в БД
     */
    public function update(Topic $topic): Topic
    {
        if ($topic->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        $modified = $topic->getModified();

        if (empty($modified)) {
            return $topic;
        }

        $values = $topic->getModelAttrs();
        $fields = $this->c->dbMap->topics;
        $set = $vars = [];

        foreach ($modified as $name) {
            if (! isset($fields[$name])) {
                continue;
            }

            $vars[] = $values[$name];
            $set[]  = $name . '=?' . $fields[$name];
        }

        if (empty($set)) {
            return $topic;
        }

        $vars[] = $topic->id;

        $set   = \implode(', ', $set);
        $query = "UPDATE ::topics
            SET {$set}
            WHERE id=?i";

        $this->c->DB->exec($query, $vars);
        $topic->resModified();

        return $topic;
    }

    /**
     * Добавляет новую тему в БД
     */
    public function insert(Topic $topic): int
    {
        if (null !== $topic->id) {
            throw new RuntimeException('The model has ID');
        }

        $attrs  = $topic->getModelAttrs();
        $fields = $this->c->dbMap->topics;
        $set = $set2 = $vars = [];

        foreach ($attrs as $key => $value) {
            if (! isset($fields[$key])) {
                continue;
            }

            $vars[] = $value;
            $set[]  = $key;
            $set2[] = '?' . $fields[$key];
        }

        if (empty($set)) {
            throw new RuntimeException('The model is empty');
        }

        $set   = \implode(', ', $set);
        $set2  = \implode(', ', $set2);
        $query = "INSERT INTO ::topics ({$set})
            VALUES ({$set2})";

        $this->c->DB->exec($query, $vars);

        $topic->id = (int) $this->c->DB->lastInsertId();

        $topic->resModified();

        return $topic->id;
    }
}
