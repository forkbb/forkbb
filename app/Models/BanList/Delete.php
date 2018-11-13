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
     * @return BanList\Model
     */
    public function delete(array $ids)
    {
        if (! empty($ids)) {
            $vars = [
                ':ids' => $ids
            ];
            $sql = 'DELETE FROM ::bans WHERE id IN (?ai:ids)';

            $this->c->DB->exec($sql, $vars);
            $this->model->load();
        }
        return $this->model;
    }
}
