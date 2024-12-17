<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Draft;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Draft\Draft;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет черновик(и)
     */
    public function delete(Forum|Draft|Topic|User ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s), Forum(s), Topic(s) or Draft(s)');
        }

        $users   = [];
        $forums  = [];
        $topics  = [];
        $drafts  = [];
        $isUser  = 0;
        $isForum = 0;
        $isTopic = 0;
        $isDraft = 0;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }

                $users[$arg->id] = $arg->id;
                $isUser          = 1;
            } elseif ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }

                $forums[$arg->id] = $arg->id;
                $isForum          = 1;
            } elseif ($arg instanceof Topic) {
                if (! $arg->parent instanceof Forum) {
                    throw new RuntimeException('Parent unavailable');
                }

                $topics[$arg->id] = $arg->id;
                $isTopic          = 1;
            } elseif ($arg instanceof Draft) {
                $drafts[$arg->id] = $arg->id;
                $isDraft          = 1;
            }
        }

        if ($isUser + $isForum + $isTopic + $isDraft > 1) {
            throw new InvalidArgumentException('Expected only User(s), Forum(s), Topic(s) or Draft(s)');
        }

        if ($users) {
            $vars = [
                ':users' => $users,
            ];
            $query = 'DELETE
                FROM ::drafts
                WHERE poster_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);
        }

        if ($forums) {
            $vars = [
                ':forums' => $forums,
            ];
            $query = 'DELETE
                FROM ::drafts
                WHERE forum_id IN (?ai:forums)';

            $this->c->DB->exec($query, $vars);
        }

        if ($topics) {
            $vars = [
                ':topics' => $topics,
            ];
            $query = 'DELETE
                FROM ::drafts
                WHERE topic_id IN (?ai:topics)';

            $this->c->DB->exec($query, $vars);
        }

        if ($drafts) {
            if (\count($drafts) > 1) {
                \sort($drafts, \SORT_NUMERIC);
            }

            $vars = [
                ':drafts' => $drafts,
            ];
            $query = 'DELETE
                FROM ::drafts
                WHERE id IN (?ai:drafts)';

            $this->c->DB->exec($query, $vars);
        }
    }
}
