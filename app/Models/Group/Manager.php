<?php

declare(strict_types=1);

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
     */
    public function get($id): ?Group
    {
        $group = parent::get($id);

        return $group instanceof Group ? $group : null;
    }

    /**
     * Обновляет группу в БД
     */
    public function update(Group $group): Group
    {
        return $this->Save->update($group);
    }

    /**
     * Добавляет новую группу в БД
     */
    public function insert(Group $group): int
    {
        $id = $this->Save->insert($group);
        $this->set($id, $group);

        return $id;
    }
}
