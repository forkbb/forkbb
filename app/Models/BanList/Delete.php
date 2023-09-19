<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use ForkBB\Models\BanList\BanList;

class Delete extends Method
{
    /**
     * Удаляет из банов записи по списку номеров
     * Обновляет кеш
     */
    public function delete(int ...$ids): BanList
    {
        if (! empty($ids)) {
            if (\count($ids) > 1) {
                \sort($ids, \SORT_NUMERIC);
            }

            $vars = [
                ':ids' => $ids,
            ];
            $query = 'DELETE
                FROM ::bans
                WHERE id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);
            $this->model->reset();
        }

        return $this->model;
    }
}
