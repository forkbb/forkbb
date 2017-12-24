<?php

namespace ForkBB\Models\Group;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\Group\Model as Group;
use RuntimeException;

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
    public function create(array $attrs = [])
    {
        return $this->c->GroupModel->setAttrs($attrs);
    }

    public function getList()
    {
        return $this->repository;
    }

    /**
     * Загрузка списка групп
     * 
     * @return Manager
     */
    public function init()
    {
        if (empty($this->flag)) {
            $stmt = $this->c->DB->query('SELECT * FROM ::groups ORDER BY g_id');
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
    public function get($id)
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
    public function update(Group $group)
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
    public function insert(Group $group)
    {
        $id = $this->Save->insert($group);
        $this->set($id, $group);
        return $id;
    }
}
