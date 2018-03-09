<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Topic\Model as Topic;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет тему(ы)
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

        foreach ($args as $arg) {
            if ($arg instanceof Post) {
                if (! $arg->parent instanceof Topic || ! $arg->parent->parent instanceof Forum) {
                    throw new RuntimeException('Parents unavailable');
                }
                $posts[$arg->id]         = $arg;
                $parents[$arg->topic_id] = $arg->parent;
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

        $this->c->search->delete(...$args);

        //???? подписки, опросы, предупреждения метки посещения тем

        $users = [];

        if ($posts) {
            foreach ($posts as $post) {
                $users[$post->poster_id] = true;
            }
            $users = \array_keys($users);

            $vars = [
                ':posts' => \array_keys($posts),
            ];
            $sql = 'DELETE FROM ::posts
                    WHERE id IN (?ai:posts)';
            $this->c->DB->exec($sql, $vars);

            $topics  = $parents;
            $parents = [];

            foreach ($topics as $topic) {
                $parents[$topic->forum_id] = $topic->parent;
                $this->c->topics->update($topic->calcStat());
            }

            foreach($parents as $forum) {
                $this->c->forums->update($forum->calcStat());
            }
        } elseif ($topics) {
            $vars = [
                ':topics' => \array_keys($topics),
            ];
            $sql = 'SELECT p.poster_id
                    FROM ::posts AS p
                    WHERE p.topic_id IN (?ai:topics)
                    GROUP BY p.poster_id';
            $users = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);

            $sql = 'DELETE FROM ::posts
                    WHERE topic_id IN (?ai:topics)';
            $this->c->DB->exec($sql, $vars);
        } elseif ($forums) {
            $vars = [
                ':forums' => \array_keys($forums),
            ];
            $sql = 'SELECT p.poster_id
                    FROM ::posts AS p
                    INNER JOIN ::topics AS t ON t.id=p.topic_id
                    WHERE t.forum_id IN (?ai:forums)
                    GROUP BY p.poster_id';
            $users = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);

            $sql = 'DELETE FROM ::posts
                    WHERE topic_id IN (
                        SELECT id
                        FROM ::topics
                        WHERE forum_id IN (?ai:forums)
                    )';
            $this->c->DB->exec($sql, $vars);
        }

        $this->c->users->updateCountPosts(...$users);
    }
}
