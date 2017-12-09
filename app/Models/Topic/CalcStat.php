<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\MethodModel;
use RuntimeException;

class CalcStat extends MethodModel
{
    /**
     * Пересчитывает статистику темы
     * 
     * @throws RuntimeException
     * 
     * @return Topic
     */
    public function calcStat()
    {
        if ($this->model->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        $vars = [
            ':tid' => $this->model->id
        ];
        $sql = 'SELECT COUNT(p.id) - 1
                FROM ::posts AS p
                WHERE p.topic_id=?i:tid';

        $num_replies = $this->c->DB->query($sql, $vars)->fetchColumn();

        $sql = 'SELECT p.id AS last_post_id, p.poster AS last_poster, p.posted, p.edited
                FROM ::posts AS p
                WHERE p.topic_id=?i:tid
                ORDER BY p.id DESC
                LIMIT 1';

        $result = $this->c->DB->query($sql, $vars)->fetch();

        //????
        $this->model->num_replies  = $num_replies;
        $this->model->last_post_id = $result['last_post_id'];
        $this->model->last_poster  = $result['last_poster'];
        $this->model->last_post    = (int) $result['edited'] > $result['posted'] ? (int) $result['edited'] : $result['posted'];

        return $this->model;
    }
}
