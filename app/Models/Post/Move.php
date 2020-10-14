<?php

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Models\Post\Model as Post;

class Move extends Action
{
    /**
     * Перенос сообщений
     */
    public function move(bool $useFrom, Topic $toTopic, Post ...$posts): void
    {
        $topics = [
            $toTopic->id => $toTopic,
        ];

        foreach ($posts as $post) {
            $topics[$post->topic_id] = $post->parent;

            if ($useFrom) {
                $post->message = "[from]{$post->parent->subject}[/from]\n" . $post->message;
            }
            $post->topic_id = $toTopic->id;
            $this->c->posts->update($post);
        }

        //???? переиндексация поискового индекса? для первого сообщения?
        //???? перерасчет количества тем у пользователей? или нет?

        $forums = [];
        foreach ($topics as $topic) {
            $forums[$topic->forum_id] = $topic->parent;

            $this->c->topics->update($topic->calcStat());
        }

        foreach ($forums as $forum) {
            $this->c->forums->update($forum->calcStat());
        }
    }
}
