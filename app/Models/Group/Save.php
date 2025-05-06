<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Group;

use ForkBB\Models\Action;
use ForkBB\Models\Group\Group;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет группу в БД
     */
    public function update(Group $group): Group
    {
        if ($group->g_id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        $modified = $group->getModified();

        if (empty($modified)) {
            return $group;
        }

        $values = $group->getModelAttrs();
        $fields = $this->c->dbMap->groups;

        $set = $vars = [];

        foreach ($modified as $name) {
            if (! isset($fields[$name])) {
                continue;
            }

            $vars[] = $values[$name];
            $set[]  = $name . '=?' . $fields[$name];
        }

        if (empty($set)) {
            return $group;
        }

        $vars[] = $group->g_id;

        $set   = \implode(', ', $set);
        $query = "UPDATE ::groups
            SET {$set}
            WHERE g_id=?i";

        $this->c->DB->exec($query, $vars);
        $group->resModified();

        // сбросить кеш для гостя
        if ($group->groupGuest) {
            $this->c->users->resetGuest();
        }

        return $group;
    }

    /**
     * Добавляет новую группу в БД
     */
    public function insert(Group $group): int
    {
        if (null !== $group->g_id) {
            throw new RuntimeException('The model has ID');
        }

        $attrs  = $group->getModelAttrs();
        $fields = $this->c->dbMap->groups;

        $set = $set2 = $vars = [];

        foreach ($attrs as $key => $value) {
            if (
                ! isset($fields[$key])
                || 'g_id' === $key
            ) {
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
        $query = "INSERT INTO ::groups ({$set})
            VALUES ({$set2})";

        $this->c->DB->exec($query, $vars);

        $group->g_id = (int) $this->c->DB->lastInsertId();

        $group->resModified();

        return $group->g_id;
    }
}
