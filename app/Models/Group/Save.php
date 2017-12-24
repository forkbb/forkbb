<?php

namespace ForkBB\Models\Group;

use ForkBB\Models\Action;
use ForkBB\Models\Group\Model as Group;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет группу в БД
     *
     * @param Group $group
     * 
     * @throws RuntimeException
     * 
     * @return Group
     */
    public function update(Group $group)
    {
        if ($group->g_id < 1) {
            throw new RuntimeException('The model does not have ID');
        }
        $modified = $group->getModified();
        if (empty($modified)) {
            return $group;
        }
        $values = $group->getAttrs();
        $fileds = $this->c->dbMap->groups;
        $set = $vars = [];
        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }
            $vars[] = $values[$name];
            $set[] = $name . '=?' . $fileds[$name];
        }
        if (empty($set)) {
            return $group;
        }
        $vars[] = $group->g_id;
        $this->c->DB->query('UPDATE ::groups SET ' . implode(', ', $set) . ' WHERE id=?i', $vars);
        $group->resModified();

        return $group;
    }

    /**
     * Добавляет новую группу в БД
     *
     * @param Group $group
     * 
     * @throws RuntimeException
     * 
     * @return int
     */
    public function insert(Group $group)
    {
        if (null !== $group->g_id) {
            throw new RuntimeException('The model has ID');
        }
        $attrs  = $group->getAttrs();
        $fileds = $this->c->dbMap->groups;
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
        $this->c->DB->query('INSERT INTO ::groups (' . implode(', ', $set) . ') VALUES (' . implode(', ', $set2) . ')', $vars);
        $group->g_id = $this->c->DB->lastInsertId();
        $group->resModified();

        return $group->g_id;
    }
}
