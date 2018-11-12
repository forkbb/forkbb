<?php

namespace ForkBB\Models\Stats;

use ForkBB\Models\Model as BaseModel;
use PDO;

class Model extends BaseModel
{
    /**
     * Загружает статистику из кеша/БД
     *
     * @return Models\Stats
     */
    public function init()
    {
        if ($this->c->Cache->has('stats')) {
            $list = $this->c->Cache->get('stats');
        } else {
            $list = $this->c->users->stats();
            $this->c->Cache->set('stats', $list);
        }
        $this->userTotal = $list['total'];
        $this->userLast  = $list['last'];

        list($this->topicTotal, $this->postTotal) = $this->c->DB->query('SELECT SUM(f.num_topics), SUM(f.num_posts) FROM ::forums AS f')->fetch(PDO::FETCH_NUM);

        return $this;
    }

    /**
     * Сбрасывает кеш статистики
     *
     * @return Models\Stats
     */
    public function reset()
    {
        $this->c->Cache->delete('stats');
        return $this;
    }
}
