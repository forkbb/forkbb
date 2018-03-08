<?php

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Topic\Model as Topic;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Method
{
    /**
     * Удаление индекса
     *
     * @param mixed ...$args
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(...$args)
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected forum, topic or post');
        }

        $posts   = [];
        $parents = [];
        $topics  = [];
        $forums  = [];
        // ?????
        foreach ($args as $arg) {
            if ($arg instanceof Post) {
                if (! $arg->parent instanceof Topic || ! $arg->parent->parent instanceof Forum) {
                    throw new RuntimeException('Parents unavailable');
                }
                $posts[$arg->id]         = $arg;
            } elseif ($arg instanceof Topic) {
                if (! $arg->parent instanceof Forum) {
                    throw new RuntimeException('Parent unavailable');
                }
                $topics[$arg->id] = $arg;
            } elseif ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }
                $forums[$arg->id] = $arg;
            } else {
                throw new InvalidArgumentException('Expected forum, topic or post');
            }
        }

        if (! empty($posts) + ! empty($topics) + ! empty($forums) > 1) {
            throw new InvalidArgumentException('Expected only forum, topic or post');
        }

        if ($posts) {
            $vars = [
                ':posts' => \array_keys($posts),
            ];
            $sql = 'DELETE FROM ::search_matches
                    WHERE post_id IN (?ai:posts)';
        } elseif ($topics) {
            $vars = [
                ':topics' => \array_keys($topics),
            ];
            $sql = 'DELETE FROM ::search_matches
                    WHERE post_id IN (
                        SELECT p.id
                        FROM ::posts AS p
                        WHERE p.topic_id IN (?ai:topics)
                    )';
        } elseif ($forums) {
            $vars = [
                ':forums' => \array_keys($forums),
            ];
            $sql = 'DELETE FROM ::search_matches
                    WHERE post_id IN (
                        SELECT p.id
                        FROM ::posts AS p
                        INNER JOIN ::topics AS t ON t.id=p.topic_id
                        WHERE t.forum_id IN (?ai:forums)
                    )';
        }
        $this->c->DB->exec($sql, $vars);
    }
}
