<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Post\Post;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Method
{
    /**
     * Удаление индекса
     */
    public function delete(Forum|Post|Topic|User ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s), Forum(s), Topic(s) or Post(s)');
        }

        $uids    = [];
        $fids    = [];
        $tids    = [];
        $pids    = [];
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
                    $uids[$arg->id] = $arg->id;
                }

                $isUser = 1;
            } elseif ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }

                $fids[$arg->id] = $arg->id;
                $isForum        = 1;
            } elseif ($arg instanceof Topic) {
                if (! $arg->parent instanceof Forum) {
                    throw new RuntimeException('Parent unavailable');
                }

                $tids[$arg->id] = $arg->id;
                $isTopic        = 1;
            } elseif ($arg instanceof Post) {
                if (
                    ! $arg->parent instanceof Topic
                    || ! $arg->parent->parent instanceof Forum
                ) {
                    throw new RuntimeException('Parents unavailable');
                }

                $pids[$arg->id] = $arg->id;
                $isPost         = 1;
            }
        }

        if ($isUser + $isForum + $isTopic + $isPost > 1) {
            throw new InvalidArgumentException('Expected only User(s), Forum(s), Topic(s) or Post(s)');
        }

        if ($uids) {
            $vars = [
                ':users' => $uids,
            ];
            $query = match ($this->c->DB->getType()) {
                'mysql' => 'DELETE sm
                    FROM ::search_matches AS sm, ::posts AS p
                    WHERE p.poster_id IN (?ai:users) AND sm.post_id=p.id',

                default => 'DELETE
                    FROM ::search_matches
                    WHERE post_id IN (
                        SELECT p.id
                        FROM ::posts AS p
                        WHERE p.poster_id IN (?ai:users)
                    )',
            };
        }

        if ($fids) {
            $vars = [
                ':forums' => $fids,
            ];
            $query = match ($this->c->DB->getType()) {
                'mysql' => 'DELETE sm
                    FROM ::search_matches AS sm, ::posts AS p, ::topics AS t
                    WHERE t.forum_id IN (?ai:forums) AND p.topic_id=t.id AND sm.post_id=p.id',

                default => 'DELETE
                    FROM ::search_matches
                    WHERE post_id IN (
                        SELECT p.id
                        FROM ::posts AS p
                        INNER JOIN ::topics AS t ON t.id=p.topic_id
                        WHERE t.forum_id IN (?ai:forums)
                    )',
            };
        }

        if ($tids) {
            $vars = [
                ':topics' => $tids,
            ];
            $query = match ($this->c->DB->getType()) {
                'mysql' => 'DELETE sm
                    FROM ::search_matches AS sm, ::posts AS p
                    WHERE p.topic_id IN (?ai:topics) AND sm.post_id=p.id',

                default => 'DELETE
                    FROM ::search_matches
                    WHERE post_id IN (
                        SELECT p.id
                        FROM ::posts AS p
                        WHERE p.topic_id IN (?ai:topics)
                    )',
            };
        }

        if ($pids) {
            if (\count($pids) > 1) {
                \sort($pids, \SORT_NUMERIC);
            }

            $vars = [
                ':posts' => $pids,
            ];
            $query = 'DELETE
                FROM ::search_matches
                WHERE post_id IN (?ai:posts)';
        }

        if (isset($query, $vars)) {
            $this->c->DB->exec($query, $vars);
        }
    }
}
