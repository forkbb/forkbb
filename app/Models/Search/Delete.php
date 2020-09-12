<?php

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;
use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Models\User\Model as User;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Method
{
    /**
     * Удаление индекса
     */
    public function delete(DataModel ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s), Forum(s), Topic(s) or Post(s)');
        }

        $users   = [];
        $forums  = [];
        $topics  = [];
        $posts   = [];
        $isUser  = 0;
        $isForum = 0;
        $isTopic = 0;
        $isPost  = 0;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }
                if (true === $arg->deleteAllPost) {
                    $users[] = $arg->id;
                }
                $isUser = 1;
            } elseif ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }
                $forums[$arg->id] = $arg;
                $isForum          = 1;
            } elseif ($arg instanceof Topic) {
                if (! $arg->parent instanceof Forum) {
                    throw new RuntimeException('Parent unavailable');
                }
                $topics[$arg->id] = $arg;
                $isTopic          = 1;
            } elseif ($arg instanceof Post) {
                if (
                    ! $arg->parent instanceof Topic
                    || ! $arg->parent->parent instanceof Forum
                ) {
                    throw new RuntimeException('Parents unavailable');
                }
                $posts[$arg->id] = $arg;
                $isPost          = 1;
            } else {
                throw new InvalidArgumentException('Expected User(s), Forum(s), Topic(s) or Post(s)');
            }
        }

        if ($isUser + $isForum + $isTopic + $isPost > 1) {
            throw new InvalidArgumentException('Expected only User(s), Forum(s), Topic(s) or Post(s)');
        }

        $query = null;

        if ($users) {
            $vars  = [
                ':users' => $users,
            ];
            $query = 'DELETE
                FROM ::search_matches
                WHERE post_id IN (
                    SELECT p.id
                    FROM ::posts AS p
                    WHERE p.poster_id IN (?ai:users)
                )';
        }
        if ($forums) {
            $vars  = [
                ':forums' => \array_keys($forums),
            ];
            $query = 'DELETE
                FROM ::search_matches
                WHERE post_id IN (
                    SELECT p.id
                    FROM ::posts AS p
                    INNER JOIN ::topics AS t ON t.id=p.topic_id
                    WHERE t.forum_id IN (?ai:forums)
                )';
        }
        if ($topics) {
            $vars  = [
                ':topics' => \array_keys($topics),
            ];
            $query = 'DELETE
                FROM ::search_matches
                WHERE post_id IN (
                    SELECT p.id
                    FROM ::posts AS p
                    WHERE p.topic_id IN (?ai:topics)
                )';
        }
        if ($posts) {
            $vars  = [
                ':posts' => \array_keys($posts),
            ];
            $query = 'DELETE
                FROM ::search_matches
                WHERE post_id IN (?ai:posts)';
        }
        if ($query) {
            $this->c->DB->exec($query, $vars);
        }
    }
}
