<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;

class Stats extends Model
{
    /**
     * Загружает статистику из кеша/БД
     *
     * @return Stats
     */
    public function init()
    {
        if ($this->c->Cache->has('stats')) {
            $list = $this->c->Cache->get('stats');
            $this->userTotal = $list['total'];
            $this->userLast  = $list['last'];
        } else {
            $this->load();
        }

        list($topics, $posts) = $this->c->DB->query('SELECT SUM(num_topics), SUM(num_posts) FROM ::forums')->fetch(\PDO::FETCH_NUM);
        $this->postTotal  = $posts;
        $this->topicTotal = $topics;

        return $this;
    }
}
