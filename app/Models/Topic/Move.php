<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;

class Move extends Action
{
    /**
     * Перенос тем
     */
    public function move(bool $redirect, Forum $toForum, Topic ...$topics): void
    {
        $forums = [
            $toForum->id => $toForum,
        ];

        foreach ($topics as $topic) {
            if ($topic->parent === $toForum) {
                continue;
            }
            if ($redirect) {
                $rTopic                 = $this->c->topics->create();
                $rTopic->poster         = $topic->poster;
                $rTopic->poster_id      = $topic->poster_id;
                $rTopic->subject        = $topic->subject;
                $rTopic->posted         = $topic->posted;
//                $rTopic->first_post_id  = $topic->first_post_id;
                $rTopic->last_post      = $topic->last_post;
//                $rTopic->last_post_id   = $topic->last_post_id;
//                $rTopic->last_poster    = $topic->last_poster;
//                $rTopic->last_poster_id = $topic->last_poster_id;
                $rTopic->moved_to       = $topic->id;
                $rTopic->forum_id       = $topic->forum_id;

                $this->c->topics->insert($rTopic);
            }

            $forums[$topic->forum_id] = $topic->parent;
            $topic->forum_id          = $toForum->id;

            $this->c->topics->update($topic);
        }

        foreach ($forums as $forum) {
            $this->c->forums->update($forum->calcStat());
        }
    }
}
