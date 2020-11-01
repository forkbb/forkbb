<?php

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Models\User\Model as User;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет сообщение(я)
     */
    public function delete(DataModel ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s), Forum(s), Topic(s) or Post(s)');
        }

        $users        = [];
        $usersToGuest = [];
        $usersDel     = [];
        $forums       = [];
        $topics       = [];
        $posts        = [];
        $parents      = [];
        $isUser       = 0;
        $isForum      = 0;
        $isTopic      = 0;
        $isPost       = 0;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }
                if (true === $arg->deleteAllPost) {
                    $usersDel[] = $arg->id;
                } else {
                    $usersToGuest[] = $arg->id;
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
                $posts[$arg->id]         = $arg->id;
                $parents[$arg->topic_id] = $arg->parent;
                $users[$arg->poster_id]  = $arg->poster_id;
                $isPost                  = 1;
            } else {
                throw new InvalidArgumentException('Expected User(s), Forum(s), Topic(s) or Post(s)');
            }
        }

        if ($isUser + $isForum + $isTopic + $isPost > 1) {
            throw new InvalidArgumentException('Expected only User(s), Forum(s), Topic(s) or Post(s)');
        }

        $this->c->search->delete(...$args);

        //???? подписки, опросы, предупреждения

        if ($usersToGuest) {
            $vars  = [
                ':users' => $usersToGuest,
            ];
            $query = 'UPDATE ::posts
                SET poster_id=1
                WHERE poster_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);

            $query = 'UPDATE ::posts
                SET editor_id=1
                WHERE editor_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);
        }
        if ($usersDel) {
            $vars  = [
                ':users' => $usersDel,
            ];
            $query = 'UPDATE ::posts
                SET editor_id=1
                WHERE editor_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);

            $query = 'SELECT p.topic_id
                FROM ::posts as p
                WHERE p.poster_id IN (?ai:users)
                GROUP BY p.topic_id';

            $parents = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

            $query = 'SELECT t.id
                FROM ::topics AS t
                INNER JOIN ::posts AS p ON t.first_post_id=p.id
                WHERE p.poster_id IN (?ai:users)';

            $notUse = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

            $parents = \array_diff($parents, $notUse); //????

            $query = 'DELETE
                FROM ::posts
                WHERE poster_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);

            foreach ($parents as &$parent) {
                $parent = $this->c->topics->load($parent); //???? ааааАААААААААААААА О_о
            }
            unset($parent);
        }
        if ($forums) {
            $vars  = [
                ':forums' => \array_keys($forums),
            ];
            $query = 'SELECT p.poster_id
                FROM ::posts AS p
                INNER JOIN ::topics AS t ON t.id=p.topic_id
                WHERE t.forum_id IN (?ai:forums)
                GROUP BY p.poster_id';

            $users = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

            $query = 'DELETE
                FROM ::posts
                WHERE topic_id IN (
                    SELECT id
                    FROM ::topics
                    WHERE forum_id IN (?ai:forums)
                )';
            $this->c->DB->exec($query, $vars);
        }
        if ($topics) {
            $vars  = [
                ':topics' => \array_keys($topics),
            ];
            $query = 'SELECT p.poster_id
                FROM ::posts AS p
                WHERE p.topic_id IN (?ai:topics)
                GROUP BY p.poster_id';

            $users = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

            $query = 'DELETE
                FROM ::posts
                WHERE topic_id IN (?ai:topics)';

            $this->c->DB->exec($query, $vars);
        }
        if ($posts) {
            $vars  = [
                ':posts' => $posts,
            ];
            $query = 'DELETE
                FROM ::posts
                WHERE id IN (?ai:posts)';

            $this->c->DB->exec($query, $vars);
        }
        if ($parents) {
            $topics  = $parents;
            $parents = [];

            foreach ($topics as $topic) {
                $parents[$topic->forum_id] = $topic->parent;
                $this->c->topics->update($topic->calcStat());
            }

            foreach($parents as $forum) {
                $this->c->forums->update($forum->calcStat());
            }
        }
        if ($users) {
            $this->c->users->updateCountPosts(...$users);
        }
    }
}
