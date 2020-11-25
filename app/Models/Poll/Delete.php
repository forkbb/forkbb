<?php

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Models\Action;
use ForkBB\Models\DataModel;
use ForkBB\Models\Poll\Model as Poll;
use ForkBB\Models\Topic\Model as Topic;
use PDO;
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

        $polls   = [];
        $topics  = [];
        $isPoll  = 0;
        $isTopic = 0;

        foreach ($args as $arg) {
            if ($arg instanceof Poll) {
                $arg->parent; // проверка доступности опроса

                $polls[$arg->tid] = $arg;
                $isPoll           = 1;
            } elseif ($arg instanceof Topic) {
                if (! $arg->parent instanceof Forum) {
                    throw new RuntimeException('Parent unavailable');
                }

                $topics[$arg->id] = $arg;
                $isTopic          = 1;
            } else {
                throw new InvalidArgumentException('Expected Poll(s) or Topic(s)');
            }
        }

        if ($isPoll + $isTopic > 1) {
            throw new InvalidArgumentException('Expected only Poll(s) or Topic(s)');
        }

        $vars  = [
            ':tids' => \array_keys($polls ?: $topics),
        ];
        $query = 'DELETE
            FROM ::poll
            WHERE tid IN (?ai:tids)';

        $this->c->DB->exec($query, $vars);

        $query = 'DELETE
            FROM ::poll_voted
            WHERE tid IN (?ai:tids)';

        $this->c->DB->exec($query, $vars);
    }
}
