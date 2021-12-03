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
use ForkBB\Models\Topic\Topic;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Merge extends Action
{
    /**
     * Объединяет темы
     */
    public function merge(bool $redirect, Topic ...$topics): void
    {
        if (\count($topics) < 2) {
            throw new InvalidArgumentException('Expected at least 2 topics.');
        }

        $ids         = [];
        $users       = [];
        $forums      = [];
        $firstTopic  = null;
        $otherTopics = [];

        foreach ($topics as $topic) {
            if ($topic->moved_to) {
                throw new RuntimeException('Topic links cannot be merged');
            }

            $users[$topic->poster_id] = $topic->poster_id;
            $forums[$topic->forum_id] = $topic->parent;

            if (! $firstTopic instanceof Topic) {
                $firstTopic    = $topic;
            } elseif ($topic->first_post_id < $firstTopic->first_post_id) {
                $otherTopics[] = $firstTopic;
                $ids[]         = $firstTopic->id;
                $firstTopic    = $topic;
            } else {
                $otherTopics[] = $topic;
                $ids[]         = $topic->id;
            }
        }

        //???? перенести обработку в посты?
        $vars = [
            'start'  => "[from]",
            'end'    => "[/from]\n",
            'topics' => $ids,
        ];
        $query = 'UPDATE ::posts AS p, ::topics as t
            SET p.message=CONCAT(?s:start, t.subject, ?s:end, p.message)
            WHERE p.topic_id IN (?ai:topics) AND t.id=p.topic_id';

        $this->c->DB->exec($query, $vars);

        $vars = [
            'id'     => $firstTopic->id,
            'topics' => $ids,
        ];
        $query = 'UPDATE ::posts AS p
            SET p.topic_id=?i:id
            WHERE p.topic_id IN (?ai:topics)';

        $this->c->DB->exec($query, $vars);

        // добавить перенос подписок на первую тему?

        if ($redirect) {
            foreach ($otherTopics as $topic) {
                $topic->moved_to = $firstTopic->id;
                $this->c->topics->update($topic->calcStat());
            }

            $vars = [
                'topics' => $ids,
            ];
            $query = 'SELECT t.id
                FROM ::topics AS t
                WHERE t.moved_to IN (?ai:topics)';

            $linkTopics = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

            foreach ($linkTopics as $topic) {
                $topic->moved_to = $firstTopic->id;
                $this->c->topics->update($topic->calcStat());
            }

            $this->c->topics->update($firstTopic->calcStat());

            foreach ($forums as $forum) {
                $this->c->forums->update($forum->calcStat());
            }

            if ($users) {
                $this->c->users->UpdateCountTopics(...$users);
            }
        } else {
            $this->c->topics->update($firstTopic->calcStat());

            $this->manager->delete(...$otherTopics);

            $this->c->forums->update($firstTopic->parent->calcStat());
        }
    }
}
