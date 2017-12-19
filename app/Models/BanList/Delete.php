<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;

class Delete extends Method
{
    /**
     * Удаляет из банов записи по списку номеров
     * Обновляет кеш
     *
     * @param array $ids
     *
     * @return BanList
     */
    public function delete(array $ids)
    {
        if (! empty($ids)) {
            $this->c->DB->exec('DELETE FROM ::bans WHERE id IN (?ai:ids)', [':ids' => $ids]);
            $this->model->load();
        }
        return $this->model;
    }
}
