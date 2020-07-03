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
        $list = $this->c->DB->query('SELECT sm.text, sm.image FROM ::smilies AS sm ORDER BY sm.disp_position')->fetchAll(PDO::FETCH_KEY_PAIR); //???? text уникальное?
        $this->model->list = $list;
        $this->c->Cache->set('smilies', $list);

        return $this->model;
    }
}
