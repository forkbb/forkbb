<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;

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
