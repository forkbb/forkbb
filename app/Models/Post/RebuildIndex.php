<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Model as Topic;

class RebuildIndex extends Action
{
    /**
     * Перестройка поискового индекса
     *
     * @param int $start
     * @param int $limit
     * @param string $mode
     *
     * @return int
     */
    public function rebuildIndex(int $start, int $limit, string $mode): int
    {
        $vars = [
            ':start' => $start,
            ':limit' => $limit,
        ];

        $sql = 'SELECT p.id, p.message, t.id as topic_id, t.subject, t.first_post_id
                FROM ::posts AS p
                INNER JOIN ::topics AS t ON t.id=p.topic_id
                WHERE p.id>=?i:start
                ORDER BY p.id ASC
                LIMIT ?i:limit';

        $stmt   = $this->c->DB->query($sql, $vars);
        $number = 0;

        while ($row = $stmt->fetch()) {
            $number = $row['id'];

            $post  = $this->manager->create([
                'id'       => $row['id'],
                'message'  => $row['message'],
                'topic_id' => $row['topic_id'],
            ]);

            if (! $this->c->topics->get($row['topic_id']) instanceof Topic) {
                $topic = $this->c->topics->create([
                    'id'            => $row['topic_id'],
                    'subject'       => $row['subject'],
                    'first_post_id' => $row['first_post_id'],
                ]);
                $this->c->topics->set($topic->id, $topic);
            }

            //????????????????????????????????????
            $this->c->Parser->parseMessage($row['message']);
            $this->c->search->index($post, $mode);
        }

        return $number;
    }
}
