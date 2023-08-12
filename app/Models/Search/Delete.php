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
use ForkBB\Models\DataModel;
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
    public function delete(DataModel ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s), Forum(s), Topic(s) or Post(s)');
        }

        $uids    = [];
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
                    $uids[$arg->id] = $arg->id;
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

        if ($uids) {
            $vars = [
                ':users' => $uids,
            ];

            switch ($this->c->DB->getType()) {
                case 'mysql':
                    $query = 'DELETE sm
                        FROM ::search_matches AS sm, ::posts AS p
                        WHERE p.poster_id IN (?ai:users) AND sm.post_id=p.id';

                    break;
                default:
                    $query = 'DELETE
                        FROM ::search_matches
                        WHERE post_id IN (
                            SELECT p.id
                            FROM ::posts AS p
                            WHERE p.poster_id IN (?ai:users)
                        )';

                    break;
            }
        }

        if ($forums) {
            $vars = [
                ':forums' => \array_keys($forums),
            ];

            switch ($this->c->DB->getType()) {
                case 'mysql':
                    $query = 'DELETE sm
                        FROM ::search_matches AS sm, ::posts AS p, ::topics AS t
                        WHERE t.forum_id IN (?ai:forums) AND p.topic_id=t.id AND sm.post_id=p.id';

                    break;
                default:
                    $query = 'DELETE
                        FROM ::search_matches
                        WHERE post_id IN (
                            SELECT p.id
                            FROM ::posts AS p
                            INNER JOIN ::topics AS t ON t.id=p.topic_id
                            WHERE t.forum_id IN (?ai:forums)
                        )';

                    break;
            }
        }

        if ($topics) {
            $vars = [
                ':topics' => \array_keys($topics),
            ];

            switch ($this->c->DB->getType()) {
                case 'mysql':
                    $query = 'DELETE sm
                        FROM ::search_matches AS sm, ::posts AS p
                        WHERE p.topic_id IN (?ai:topics) AND sm.post_id=p.id';

                    break;
                default:
                    $query = 'DELETE
                        FROM ::search_matches
                        WHERE post_id IN (
                            SELECT p.id
                            FROM ::posts AS p
                            WHERE p.topic_id IN (?ai:topics)
                        )';

                    break;
            }
        }

        if ($posts) {
            $vars = [
                ':posts' => \array_keys($posts),
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
