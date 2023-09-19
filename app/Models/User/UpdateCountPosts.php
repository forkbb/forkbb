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
    protected ?bool $count = null;
    protected array $need  = [];

    /**
     * Обновляет число сообщений пользователя(ей)
     */
    public function updateCountPosts(mixed ...$args): void
    {
        $ids = [];

        foreach ($args as $arg) {
            if (
                $arg instanceof User
                && ! $arg->isGuest
            ) {
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

        if (empty($ids)) {
            $where = '';
            $vars  = [];
        } else {
            if (\count($ids) > 1) {
                \ksort($ids, \SORT_NUMERIC);
            }

            $where = ' WHERE ::users.id IN (?ai:ids)';
            $vars  = [
                ':ids' => \array_keys($ids),
            ];
        }

        if (null === $this->count) {
            if ($this->c->user->isAdmin) {
                $manager = $this->c->forums;
            } else {
                $manager = $this->c->ForumManager->init($this->c->groups->get(FORK_GROUP_ADMIN)); //???? закэшировать?
            }

            $forums = $manager->get(0)->descendants;
            $yes    = [];
            $not    = [];

            foreach ($forums as $forum) {
                if (0 === $forum->no_sum_mess) {
                    $yes[] = $forum->id;
                } else {
                    $not[] = $forum->id;
                }
            }

            $this->count = ! empty($yes);
            $this->need  = empty($not) ? [] : $yes;
        }

        if (! $this->count) {
            $query = 'UPDATE ::users
                SET num_posts = 0' . $where;

        } elseif (empty($this->need)) {
            $query = 'UPDATE ::users
                SET num_posts = COALESCE((
                    SELECT COUNT(p.id)
                    FROM ::posts AS p
                    WHERE p.poster_id=::users.id
                ), 0)' . $where;

        } else {
            $vars[':forums'] = $this->need;

            $query = 'UPDATE ::users
              SET num_posts = COALESCE((
                    SELECT COUNT(p.id)
                    FROM ::posts AS p
                    INNER JOIN ::topics AS t ON t.id=p.topic_id
                    WHERE p.poster_id=::users.id AND t.forum_id IN (?ai:forums)
                ), 0)' . $where;

        }

        $this->c->DB->exec($query, $vars);
    }
}
