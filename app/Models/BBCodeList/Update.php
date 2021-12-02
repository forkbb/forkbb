<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BBCodeList;

use ForkBB\Models\Method;
use ForkBB\Models\BBCodeList\BBCodeList;
use ForkBB\Models\BBCodeList\Structure;
use RuntimeException;

class Update extends Method
{
    /**
     * Обновляет структуру bb-кода
     */
    public function update(int $id, Structure $structure): BBCodeList
    {
        if (null !== $structure->getError()) {
            throw new RuntimeException('BBCode structure has error');
        }

        $vars = [
            ':id'        => $id,
            ':tag'       => $structure->tag,
            ':structure' => $structure->toString(),
        ];
        $query = 'UPDATE ::bbcode
            SET bb_structure=?s:structure
            WHERE id=?i:id AND bb_tag=?s:tag AND bb_edit=1';

        $this->c->DB->exec($query, $vars);

        return $this->model->reset();
    }
}
