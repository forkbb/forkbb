<?php

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;

class UpdateCountTopics extends Action
{
    /**
     * Обновляет число тем пользователя(ей)
     *
     * @param mixed ...$args
     *
     * @throws InvalidArgumentException
     */
    public function updateCountTopics(...$args): void
    {
        $ids = [];
        foreach ($args as $arg) {
            if ($arg instanceof User) {
                $ids[$arg->id] = true;
            } elseif (
                \is_int($arg)
                && $arg > 0
            ) {
                $ids[$arg] = true;
            } else {
                throw new InvalidArgumentException('Expected user or positive integer id');
            }
        }
        // темы гостя не считаем
        unset($ids[1]);

        if (empty($ids)) {
            $where = 'u.id != 1';
            $vars  = [];
        } else {
            $where = 'u.id IN (?ai:ids)';
            $vars  = [
                ':ids' => \array_keys($ids),
            ];
        }

        $query = 'UPDATE ::users AS u
            SET u.num_topics = (
                SELECT COUNT(t.id)
                FROM ::topics AS t
                INNER JOIN ::posts AS p ON t.first_post_id=p.id
                WHERE p.poster_id=u.id AND t.moved_to=0
                GROUP BY p.poster_id
            )
            WHERE ' . $where;

        $this->c->DB->exec($query, $vars);
    }
}
