<?php

namespace ForkBB\Models\Forum;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет раздел(ы)
     *
     * @param mixed ...$args
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function delete(...$args)
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected forum(s)');
        }

        $forums = [];
        $all    = [];

        foreach ($args as $arg) {
            if ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }
                $forums[$arg->id] = $arg;
                $all[$arg->id]    = true;
                foreach (\array_keys($arg->descendants) as $id) { //???? а если не админ?
                    $all[$id] = true;
                }
            } else {
                throw new InvalidArgumentException('Expected forum(s)');
            }
        }

        if (\array_diff_key($all, $forums)) {
            throw new RuntimeException('Descendants should not be or they should be deleted too');
        }

        $this->c->topics->delete(...$args);

        //???? подписки, опросы, предупреждения, метки посещения тем

        foreach ($forums as $forum) {
            $this->c->groups->Perm->reset($forum);
        }

        $vars = [
            ':forums' => \array_keys($forums),
        ];
        $sql = 'DELETE FROM ::mark_of_forum
                WHERE fid IN (?ai:forums)';
        $this->c->DB->exec($sql, $vars);

        $sql = 'DELETE FROM ::forums
                WHERE id IN (?ai:forums)';
        $this->c->DB->exec($sql, $vars);
    }
}
