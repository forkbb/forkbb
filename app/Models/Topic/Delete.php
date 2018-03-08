<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;
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
            throw new InvalidArgumentException('No arguments, expected forum or topic');
        }

        $topics  = [];
        $parents = [];
        $forums  = [];

        foreach ($args as $arg) {
            if ($arg instanceof Topic) {
                if (! $arg->parent instanceof Forum) {
                    throw new RuntimeException('Parent unavailable');
                }
                $topics[$arg->id]        = $arg;
                $parents[$arg->forum_id] = $arg->parent;
            } elseif ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }
                $forums[$arg->id] = $arg;
            } else {
                throw new InvalidArgumentException('Expected forum or topic');
            }
        }

        if (! empty($topics) + ! empty($forums) > 1) {
            throw new InvalidArgumentException('Expected only forum or topic');
        }

        $this->c->posts->delete(...$args);

        //???? подписки, опросы, предупреждения, метки посещения тем

        if ($topics) {
            $vars = [
                ':topics' => \array_keys($topics),
            ];
            $sql = 'DELETE FROM ::mark_of_topic
                    WHERE tid IN (?ai:topics)';
            $this->c->DB->exec($sql, $vars);

            $sql = 'DELETE FROM ::topics
                    WHERE id IN (?ai:topics)';
            $this->c->DB->exec($sql, $vars);

            foreach($parents as $forum) {
                $this->c->forums->update($forum->calcStat());
            }
        } elseif ($forums) {
            $vars = [
                ':forums' => \array_keys($forums),
            ];
            $sql = 'DELETE FROM ::mark_of_topic
                    WHERE tid IN (
                        SELECT id
                        FROM ::topics
                        WHERE forum_id IN (?ai:forums)
                    )';
            $this->c->DB->exec($sql, $vars);

            $sql = 'DELETE FROM ::topics
                    WHERE forum_id IN (?ai:forums)';
            $this->c->DB->exec($sql, $vars);
        }
    }
}
