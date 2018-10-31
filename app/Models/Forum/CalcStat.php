<?php

namespace ForkBB\Models\Forum;

use ForkBB\Models\Method;
use RuntimeException;

class CalcStat extends Method
{
    /**
     * Пересчитывает статистику
     *
     * @throws RuntimeException
     *
     * @return Forum
     */
    public function calcStat()
    {
        if ($this->model->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        $vars = [':fid' => $this->model->id];
        $sql = 'SELECT COUNT(t.id) as num_topics, SUM(t.num_replies) as num_replies
                FROM ::topics AS t
                WHERE t.forum_id=?i:fid';

        $result = $this->c->DB->query($sql, $vars)->fetch();

        $this->model->num_topics = $result['num_topics'];
        $this->model->num_posts  = $result['num_topics'] + $result['num_replies'];

        $sql = 'SELECT t.last_post, t.last_post_id, t.last_poster, t.subject as last_topic
                FROM ::topics AS t
                WHERE t.forum_id=?i:fid AND t.moved_to=0
                ORDER BY t.last_post DESC
                LIMIT 1';

        $result = $this->c->DB->query($sql, $vars)->fetch();

        if (empty($result)) {
            $this->model->last_post    = null;
            $this->model->last_post_id = null;
            $this->model->last_poster  = null;
            $this->model->last_topic   = null;
        } else {
            $this->model->last_post    = $result['last_post'];
            $this->model->last_post_id = $result['last_post_id'];
            $this->model->last_poster  = $result['last_poster'];
            $this->model->last_topic   = $result['last_topic'];
        }

        return $this->model;
    }
}
