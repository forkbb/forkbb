<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Model as Topic;

class RebuildIndex extends Action
{
    /**
     * Перестройка поискового индекса
     */
    public function rebuildIndex(int $start, int $limit, string $mode): int
    {
        $vars = [
            ':start' => $start,
            ':limit' => $limit,
        ];
        $query = 'SELECT p.id, p.message, t.id as topic_id, t.subject, t.first_post_id, t.forum_id
            FROM ::posts AS p
            INNER JOIN ::topics AS t ON t.id=p.topic_id
            WHERE p.id>=?i:start
            ORDER BY p.id ASC
            LIMIT ?i:limit';

        $stmt   = $this->c->DB->query($query, $vars);
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
                    'forum_id'      => $row['forum_id'],
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
