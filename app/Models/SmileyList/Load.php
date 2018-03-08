<?php

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Method;
use PDO;

class Load extends Method
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     *
     * @return SmileyList
     */
    public function load()
    {
        $list = $this->c->DB->query('SELECT text, image FROM ::smilies ORDER BY disp_position')->fetchAll(PDO::FETCH_KEY_PAIR); //???? text уникальное?
        $this->model->list = $list;
        $this->c->Cache->set('smilies', $list);
        return $this->model;
    }
}
