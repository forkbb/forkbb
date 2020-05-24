<?php

namespace ForkBB\Models\Forum;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\User\Model as User;
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
    public function delete(...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s) or Forum(s)');
        }

        $users   = [];
        $forums  = [];
        $all     = [];
        $isUser  = 0;
        $isForum = 0;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }
                $users[] = $arg->id;
                $isUser  = 1;
            } elseif ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }
                $forums[$arg->id] = $arg;
                $all[$arg->id]    = true;
                foreach (\array_keys($arg->descendants) as $id) { //???? а если не админ?
                    $all[$id] = true;
                }
                $isForum = 1;
            } else {
                throw new InvalidArgumentException('Expected User(s) or Forum(s)');
            }
        }

        if ($isUser + $isForum > 1) {
            throw new InvalidArgumentException('Expected only User(s) or Forum(s)');
        }

        if (\array_diff_key($all, $forums)) {
            throw new RuntimeException('Descendants should not be or they should be deleted too');
        }

        $this->c->topics->delete(...$args);

        //???? подписки, опросы, предупреждения

        if ($users) {
            $vars = [
                ':users' => $users,
            ];
            $sql = 'DELETE FROM ::mark_of_forum
                    WHERE uid IN (?ai:users)';
            $this->c->DB->exec($sql, $vars);

            //???? удаление модераторов из разделов
        }
        if ($forums) {
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
}
