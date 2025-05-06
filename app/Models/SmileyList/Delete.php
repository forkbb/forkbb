<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Method;
use ForkBB\Models\SmileyList\SmileyList;

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
