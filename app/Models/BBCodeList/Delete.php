<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BBCodeList;

use ForkBB\Models\Method;
use ForkBB\Models\BBCodeList\BBCodeList;

class Delete extends Method
{
    /**
     * Удаляет bbcode по id
     */
    public function delete(int $id): BBCodeList
    {
        $vars = [
            ':id' => $id,
        ];
        $query = 'DELETE
            FROM ::bbcode
            WHERE id=?i:id AND bb_delete=1';

        $this->c->DB->exec($query, $vars);

        return $this->model->reset();
    }
}
