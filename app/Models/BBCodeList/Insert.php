<?php

declare(strict_types=1);

namespace ForkBB\Models\BBCodeList;

use ForkBB\Models\Method;
use ForkBB\Models\BBCodeList\Structure;
use RuntimeException;

class Insert extends Method
{
    /**
     * Добавляет bb-код в базу
     */
    public function insert(Structure $structure): int
    {
        if (null !== $structure->getError()) {
            throw new RuntimeException('BBCode structure has error');
        }

        $this->model->reset(); // ????

        $vars = [
            ':tag'       => $structure->tag,
            ':structure' => $structure->toString(),
        ];
        $query = 'INSERT INTO ::bbcode (bb_tag, bb_edit, bb_delete, bb_structure)
            VALUES (?s:tag, 1, 1, ?s:structure)';

        $this->c->DB->exec($query, $vars);

        return (int) $this->c->DB->lastInsertId();
    }
}
