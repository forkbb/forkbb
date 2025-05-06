<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Post\Post;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет сообщение(я)
     */
    public function delete(Forum|Post|Topic|User ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s), Forum(s), Topic(s) or Post(s)');
        }

        $uidsToGuest = [];
        $uidsDelete  = [];
        $uidsUpdate  = [];
        $forums      = [];
        $topics      = [];
        $pids        = [];
        $parents     = [];
        $isUser      = 0;
        $isForum     = 0;
        $isTopic     = 0;
        $isPost      = 0;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }

                if (true === $arg->deleteAllPost) {
                    $uidsDelete[$arg->id] = $arg->id;

                } else {
                    $uidsToGuest[$arg->id] = $arg->id;
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

                $pids[$arg->id]          = $arg->id;
                $parents[$arg->topic_id] = $arg->parent;
                $isPost                  = 1;

                if ($arg->poster_id > 0) {
                    $uidsUpdate[$arg->poster_id] = $arg->poster_id;
                }
            }
        }

        if ($isUser + $isForum + $isTopic + $isPost > 1) {
            throw new InvalidArgumentException('Expected only User(s), Forum(s), Topic(s) or Post(s)');
        }

        $this->c->search->delete(...$args);
        $this->c->reactions->delete(...$args);

        //???? предупреждения

        if ($uidsToGuest) {
            $vars = [
                ':users' => $uidsToGuest,
            ];
            $query = 'UPDATE ::posts
                SET poster_id=0
                WHERE poster_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);

            $query = 'UPDATE ::posts
                SET editor_id=0
                WHERE editor_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);
        }

        if ($uidsDelete) {
            $vars = [
                ':users' => $uidsDelete,
            ];
            $query = 'SELECT p.topic_id
                FROM ::posts as p
                WHERE p.poster_id IN (?ai:users)
                GROUP BY p.topic_id';

            $tids = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

//            $query = 'SELECT t.id
//                FROM ::topics AS t
//                WHERE t.poster_id IN (?ai:users)';
//
//            $notUse = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN); // эти темы удаляются
//
//            $tids = \array_diff($tids, $notUse);

            $parents = $this->c->topics->loadByIds($tids, false);

            $query = 'DELETE
                FROM ::posts
                WHERE poster_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);

            $query = 'UPDATE ::posts
                SET editor_id=0
                WHERE editor_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);
        }

        if ($forums) {
            $vars = [
                ':forums' => \array_keys($forums),
            ];
            $query = 'SELECT p.poster_id
                FROM ::posts AS p
                INNER JOIN ::topics AS t ON t.id=p.topic_id
                WHERE t.forum_id IN (?ai:forums) AND p.poster_id>0
                GROUP BY p.poster_id';

            $uidsUpdate = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

            $query = match ($this->c->DB->getType()) {
                'mysql' => 'DELETE p
                    FROM ::posts AS p, ::topics AS t
                    WHERE t.forum_id IN (?ai:forums) AND p.topic_id=t.id',

                default => 'DELETE
                    FROM ::posts
                    WHERE topic_id IN (
                        SELECT id
                        FROM ::topics
                        WHERE forum_id IN (?ai:forums)
                    )',
            };

            $this->c->DB->exec($query, $vars);
        }

        if ($topics) {
            $vars = [
                ':topics' => \array_keys($topics),
            ];
            $query = 'SELECT p.poster_id
                FROM ::posts AS p
                WHERE p.topic_id IN (?ai:topics) AND p.poster_id>0
                GROUP BY p.poster_id';

            $uidsUpdate = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

            $query = 'DELETE
                FROM ::posts
                WHERE topic_id IN (?ai:topics)';

            $this->c->DB->exec($query, $vars);
        }

        if ($pids) {
            if (\count($pids) > 1) {
                \sort($pids, \SORT_NUMERIC);
            }

            $vars = [
                ':posts' => $pids,
            ];
            $query = 'DELETE
                FROM ::posts
                WHERE id IN (?ai:posts)';

            $this->c->DB->exec($query, $vars);

            foreach ($parents as $topic) {
                $topic->deleteFromToc(...$pids);
            }
        }

        if ($parents) {
            if (\count($parents) > 1) {
                \ksort($parents, \SORT_NUMERIC);
            }

            $topics  = $parents;
            $parents = [];

            foreach ($topics as $topic) {
                $parents[$topic->parent->id] = $topic->parent;

                $this->c->topics->update($topic->calcStat());
            }

            if (! $forums) {
                if (\count($parents) > 1) {
                    \ksort($parents, \SORT_NUMERIC);
                }

                foreach ($parents as $forum) {
                    $this->c->forums->update($forum->calcStat());
                }
            }
        }

        if ($uidsUpdate) {
            $this->c->users->updateCountPosts(...$uidsUpdate);
        }
    }
}
