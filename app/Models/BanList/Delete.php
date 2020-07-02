<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use ForkBB\Models\BanList\Model as BanList;

class Delete extends Method
{
    /**
     * Удаляет из банов записи по списку номеров
     * Обновляет кеш
     *
     * @param int ...$ids
     *
     * @return BanList\Model
     */
    public function delete(int ...$ids): BanList
    {
        if (! empty($ids)) {
            $vars  = [
                ':ids' => $ids,
            ];
            $query = 'DELETE
                FROM ::bans
                WHERE id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);
            $this->model->load();
        }

        return $this->model;
    }
}
