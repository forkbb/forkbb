<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Group;

use ForkBB\Models\Manager;
use ForkBB\Models\Group\Group;

class Groups extends Manager
{
    /**
     * Ключ модели для контейнера
     * @var string
     */
    protected $cKey = 'Groups';

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
    public function init(): Groups
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
        return $this->save->update($group);
    }

    /**
     * Добавляет новую группу в БД
     */
    public function insert(Group $group): int
    {
        $id = $this->save->insert($group);
        $this->set($id, $group);

        return $id;
    }
}
