<?php

namespace ForkBB\Models\BBCodeList;

use ForkBB\Models\Method;
use ForkBB\Models\BBCodeList\Model as BBCodeList;
use PDO;

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
