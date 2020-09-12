<?php

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Method;
use ForkBB\Models\SmileyList\Model as SmileyList;
use PDO;

class Load extends Method
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     */
    public function load(): SmileyList
    {
        $query = 'SELECT id, sm_code, sm_image, sm_position
            FROM ::smilies
            ORDER BY sm_position';

        $list = $this->c->DB->query($query)->fetchAll(PDO::FETCH_UNIQUE);

        $this->model->list = $list;

        $this->c->Cache->set('smilies', $list);

        return $this->model;
    }
}
