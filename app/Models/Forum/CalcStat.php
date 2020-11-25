<?php

declare(strict_types=1);

namespace ForkBB\Models\Forum;

use ForkBB\Models\Method;
use ForkBB\Models\Forum\Model as Forum;
use RuntimeException;

class CalcStat extends Method
{
    /**
     * Пересчитывает статистику раздела
     */
    public function calcStat(): Forum
    {
        if ($this->model->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        $vars = [
            ':fid' => $this->model->id,
        ];
        $query = 'SELECT COUNT(t.id)
            FROM ::topics AS t
            WHERE t.forum_id=?i:fid AND t.moved_to!=0';

        $moved = $this->c->DB->query($query, $vars)->fetchColumn();

        $query = 'SELECT COUNT(t.id) as num_topics, SUM(t.num_replies) as num_replies
            FROM ::topics AS t
            WHERE t.forum_id=?i:fid AND t.moved_to=0';

        $result = $this->c->DB->query($query, $vars)->fetch();

        $this->model->num_topics = $result['num_topics'] + $moved;
        $this->model->num_posts  = $result['num_topics'] + $result['num_replies'];

        $query = 'SELECT t.last_post, t.last_post_id, t.last_poster, t.last_poster_id, t.subject as last_topic
            FROM ::topics AS t
            WHERE t.forum_id=?i:fid AND t.moved_to=0
            ORDER BY t.last_post DESC
            LIMIT 1';

        $result = $this->c->DB->query($query, $vars)->fetch();

        if (empty($result)) {
            $this->model->last_post      = 0;
            $this->model->last_post_id   = 0;
            $this->model->last_poster    = 0;
            $this->model->last_poster_id = 0;
            $this->model->last_topic     = 0;
        } else {
            $this->model->last_post      = $result['last_post'];
            $this->model->last_post_id   = $result['last_post_id'];
            $this->model->last_poster    = $result['last_poster'];
            $this->model->last_poster_id = $result['last_poster_id'];
            $this->model->last_topic     = $result['last_topic'];
        }

        return $this->model;
    }
}
