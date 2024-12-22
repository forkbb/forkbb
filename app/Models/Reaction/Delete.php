<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Reaction;

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
     * Удаляет реакции на сообщение(я)
     */
    public function delete(Forum|Post|Topic|User ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s), Forum(s), Topic(s) or Post(s)');
        }

        $ids     = [];
        $isUser  = 0;
        $isForum = 0;
        $isTopic = 0;
        $isPost  = 0;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }

                $ids[$arg->id] = $arg->id;
                $isUser        = 1;

            } elseif ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }

                $ids[$arg->id] = $arg->id;
                $isForum       = 1;

            } elseif ($arg instanceof Topic) {
                if (! $arg->parent instanceof Forum) {
                    throw new RuntimeException('Parent unavailable');
                }

                $ids[$arg->id] = $arg->id;
                $isTopic       = 1;

            } elseif ($arg instanceof Post) {
                if (
                    ! $arg->parent instanceof Topic
                    || ! $arg->parent->parent instanceof Forum
                ) {
                    throw new RuntimeException('Parents unavailable');
                }

                $ids[$arg->id] = $arg->id;
                $isPost        = 1;
            }
        }

        if ($isUser + $isForum + $isTopic + $isPost > 1) {
            throw new InvalidArgumentException('Expected only User(s), Forum(s), Topic(s) or Post(s)');
        }

        \sort($ids, \SORT_NUMERIC);

        $vars = [
            ':ids' => $ids,
        ];

        if ($isUser > 0) {
            $query = 'DELETE
                FROM ::reactions
                WHERE uid IN (?ai:ids)';

        } elseif ($isPost > 0) {
            $query = 'DELETE
                FROM ::reactions
                WHERE pid IN (?ai:ids)';

        } elseif ($isTopic > 0) {
            $query = match ($this->c->DB->getType()) {
                'mysql' => 'DELETE r
                    FROM ::reactions AS r, ::posts AS p
                    WHERE p.topic_id IN (?ai:ids) AND r.pid=p.id',

                default => 'DELETE
                    FROM ::reactions
                    WHERE pid IN (
                        SELECT id
                        FROM ::posts
                        WHERE topic_id IN (?ai:ids)
                    )',
            };

        } elseif ($isForum > 0) {
            $query = match ($this->c->DB->getType()) {
                'mysql' => 'DELETE r
                    FROM ::reactions AS r, ::posts AS p, ::topics AS t
                    WHERE t.forum_id IN (?ai:ids) AND p.topic_id=t.id AND r.pid=p.id',

                default => 'DELETE
                    FROM ::reactions
                    WHERE pid IN (
                        SELECT p.id
                        FROM ::posts AS p
                        INNER JOIN ::topics AS t ON p.topic_id=t.id
                        WHERE t.forum_id IN (?ai:ids)
                    )',
            };

        }

        $this->c->DB->exec($query, $vars);
    }
}
