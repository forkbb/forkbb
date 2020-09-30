<?php

namespace ForkBB\Models\BBCodeList;

use ForkBB\Models\Method;
use ForkBB\Models\BBCodeList\Model as BBCodeList;
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
