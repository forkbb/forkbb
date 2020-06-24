<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;

class Move extends Action
{
    /**
     * Перенос тем
     *
     * @param bool $redirect
     * @param Forum $toForum
     * @param Topic ...$topics
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
                $rTopic              = clone $topic;
                $rTopic->id          = null;
                $rTopic->moved_to    = $topic->id;
                $rTopic->num_replies = 0;

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
