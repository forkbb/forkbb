<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Models\Action;
use ForkBB\Models\User\User;
use InvalidArgumentException;

class UpdateCountPosts extends Action
{
    /**
     * Обновляет число сообщений пользователя(ей)
     */
    public function updateCountPosts(/* mixed */ ...$args): void
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
        // сообщения гостя не считаем
        unset($ids[0]);

        if (empty($ids)) {
            $where = 'u.id > 0';
            $vars  = [];
        } else {
            $where = 'u.id IN (?ai:ids)';
            $vars  = [
                ':ids' => \array_keys($ids),
            ];
        }

        $query = 'UPDATE ::users AS u
            SET u.num_posts = COALESCE((
                SELECT COUNT(p.id)
                FROM ::posts AS p
                INNER JOIN ::topics AS t ON t.id=p.topic_id
                INNER JOIN ::forums AS f ON f.id=t.forum_id
                WHERE p.poster_id=u.id AND f.no_sum_mess=0
                GROUP BY p.poster_id
            ), 0)
            WHERE ' . $where;

        $this->c->DB->exec($query, $vars);
    }
}
