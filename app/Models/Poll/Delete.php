<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Models\Action;
use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Poll\Poll;
use ForkBB\Models\Topic\Topic;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаление индекса
     */
    public function delete(DataModel ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected Poll(s) or Topic(s)');
        }

        $tids    = [];
        $isPoll  = 0;
        $isTopic = 0;

        foreach ($args as $arg) {
            if ($arg instanceof Poll) {
                $arg->parent; // проверка доступности опроса

                $tids[$arg->tid] = $arg->tid;
                $isPoll          = 1;
            } elseif ($arg instanceof Topic) {
                if (! $arg->parent instanceof Forum) {
                    throw new RuntimeException('Parent unavailable');
                }

                $tids[$arg->id] = $arg->id;
                $isTopic        = 1;
            } else {
                throw new InvalidArgumentException('Expected Poll(s) or Topic(s)');
            }
        }

        if ($isPoll + $isTopic > 1) {
            throw new InvalidArgumentException('Expected only Poll(s) or Topic(s)');
        }

        $vars = [
            ':tids' => $tids,
        ];
        $query = 'DELETE
            FROM ::poll
            WHERE tid IN (?ai:tids)';

        $this->c->DB->exec($query, $vars);

        $query = 'DELETE
            FROM ::poll_voted
            WHERE tid IN (?ai:tids)';

        $this->c->DB->exec($query, $vars);

        foreach ($tids as $tid) {
            $this->manager->reset($tid);
        }
    }
}
