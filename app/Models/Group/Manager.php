<?php

namespace ForkBB\Models\Group;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\Group\Model as Group;

class Manager extends ManagerModel
{
    /**
     * Флаг загрузки групп
     * @var bool
     */
    protected $flag;

    /**
     * Создает новую модель раздела
     *
     * @param array $attrs
     *
     * @return Group
     */
    public function create(array $attrs = []): Group
    {
        return $this->c->GroupModel->setAttrs($attrs);
    }

    public function getList(): array
    {
        return $this->repository;
    }

    /**
     * Загрузка списка групп
     *
     * @return Manager
     */
    public function init(): Manager
    {
        if (empty($this->flag)) {
            $query = 'SELECT g.*
                FROM ::groups AS g
                ORDER BY g.g_id';

            $stmt = $this->c->DB->query($query);
            while ($row = $stmt->fetch()) {
                $this->set($row['g_id'], $this->create($row));
            }
            $this->flag = true;
        }

        return $this;
    }

    /**
     * Получение модели группы
     *
     * @param int $id
     *
     * @return null|Group
     */
    public function get($id): ?Group
    {
        $group = parent::get($id);

        return $group instanceof Group ? $group : null;
    }

    /**
     * Обновляет группу в БД
     *
     * @param Group $group
     *
     * @return Group
     */
    public function update(Group $group): Group
    {
        return $this->Save->update($group);
    }

    /**
     * Добавляет новую группу в БД
     *
     * @param Group $group
     *
     * @return int
     */
    public function insert(Group $group): int
    {
        $id = $this->Save->insert($group);
        $this->set($id, $group);

        return $id;
    }
}
