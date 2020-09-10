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
     *
     * @return SmileyList\Model
     */
    public function load(): SmileyList
    {
        $query = 'SELECT sm.id, sm.text, sm.image, sm.disp_position
            FROM ::smilies AS sm
            ORDER BY sm.disp_position';

        $list = $this->c->DB->query($query)->fetchAll(PDO::FETCH_UNIQUE);

        $this->model->list = $list;

        $this->c->Cache->set('smilies', $list);

        return $this->model;
    }
}
