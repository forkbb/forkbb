<?php

namespace ForkBB\Models\Stats;

use ForkBB\Models\Model as ParentModel;
use PDO;

class Model extends ParentModel
{
    /**
     * Загружает статистику из кеша/БД
     */
    public function init(): Model
    {
        if ($this->c->Cache->has('stats')) {
            $list = $this->c->Cache->get('stats');
        } else {
            $list = $this->c->users->stats();
            $this->c->Cache->set('stats', $list);
        }
        $this->userTotal = $list['total'];
        $this->userLast  = $list['last'];

        $query = 'SELECT SUM(f.num_topics), SUM(f.num_posts)
            FROM ::forums AS f';

        list($this->topicTotal, $this->postTotal) = $this->c->DB->query($query)->fetch(PDO::FETCH_NUM);

        return $this;
    }

    /**
     * Сбрасывает кеш статистики
     */
    public function reset(): Model
    {
        $this->c->Cache->delete('stats');

        return $this;
    }
}
