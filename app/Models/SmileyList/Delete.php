<?php

declare(strict_types=1);

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Method;
use ForkBB\Models\SmileyList\Model as SmileyList;

class Delete extends Method
{
    /**
     * Удаляет смайл из БД
     * Удаляет кеш смайлов
     */
    public function delete(int $id): SmileyList
    {
        $vars = [
            ':id' => $id,
        ];
        $query = 'DELETE
            FROM ::smilies
            WHERE id=?i:id';

        $this->c->DB->exec($query, $vars);

        return $this->model->reset();
    }
}
