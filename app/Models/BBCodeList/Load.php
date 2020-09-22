<?php

namespace ForkBB\Models\BBCodeList;

use ForkBB\Models\Method;
use ForkBB\Models\BBCodeList\Model as BBCodeList;
use PDO;

class Load extends Method
{
    /**
     * Загружает таблицу bbcode в массив
     */
    public function load(): BBCodeList
    {
        $query = 'SELECT id, bb_tag, bb_edit, bb_delete, bb_structure
            FROM ::bbcode
            ORDER BY id';

        $this->model->bbcodeTable = $this->c->DB->query($query)->fetchAll(PDO::FETCH_UNIQUE);

        return $this->model;
    }
}
