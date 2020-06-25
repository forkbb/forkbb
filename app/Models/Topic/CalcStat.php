<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Method;
use ForkBB\Models\Topic\Model as Topic;
use RuntimeException;

class CalcStat extends Method
{
    /**
     * Пересчитывает статистику темы
     *
     * @throws RuntimeException
     *
     * @return Topic
     */
    public function calcStat(): Topic
    {
        if ($this->model->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        if ($this->model->moved_to) {
            $num_replies = 0;
        } else {
            $vars = [
                ':tid' => $this->model->id
            ];
            $sql = 'SELECT COUNT(p.id) - 1
                    FROM ::posts AS p
                    WHERE p.topic_id=?i:tid';

            $num_replies = $this->c->DB->query($sql, $vars)->fetchColumn();

            $sql = 'SELECT p.id, p.poster, p.poster_id, p.posted
                    FROM ::posts AS p
                    WHERE p.topic_id=?i:tid
                    ORDER BY p.id
                    LIMIT 1';

            $result = $this->c->DB->query($sql, $vars)->fetch();

            $this->model->poster        = $result['poster'];
            $this->model->poster_id     = $result['poster_id'];
            $this->model->posted        = $result['posted'];
            $this->model->first_post_id = $result['id'];

            $sql = 'SELECT p.id, p.poster, p.poster_id, p.posted, p.edited
                    FROM ::posts AS p
                    WHERE p.topic_id=?i:tid
                    ORDER BY p.id DESC
                    LIMIT 1';

            $result = $this->c->DB->query($sql, $vars)->fetch();

            $this->model->last_post_id   = $result['id'];
            $this->model->last_poster    = $result['poster'];
            $this->model->last_poster_id = $result['poster_id'];
            $this->model->last_post      = $result['edited'] > 0 && $result['edited'] > $result['posted'] ? $result['edited'] : $result['posted'];
        }

        //????
        $this->model->num_replies = $num_replies;

        return $this->model;
    }
}
