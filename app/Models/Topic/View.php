<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Search\Search;
use ForkBB\Models\Topic\Topic;
use PDO;
use RuntimeException;

class View extends Action
{
    /**
     * Возвращает список тем
     */
    public function view(Forum|Search $arg): array
    {
        if ($arg instanceof Forum) {
            $full = false;

        } elseif ($arg instanceof Search) {
            $full = true;
        }

        if (
            empty($arg->idsList)
            || ! \is_array($arg->idsList)
        ) {
            throw new RuntimeException('Model does not contain of topics list for display');
        }

        $result = $this->c->topics->loadByIds($arg->idsList, $full);

        if (
            $this->c->user->isGuest
            || 1 !== $this->c->config->b_show_dot
        ) {
            return $result;
        }

        $uid = $this->c->user->id;

        if ('topics_with_your_posts' === $arg->currentAction) {
            $dots = $arg->idsList;

        } else {
            $ids = [];

            foreach ($arg->idsList as $id) {
                if (! $result[$id] instanceof Topic) {
                    continue;
                }

                if (
                    $uid === $result[$id]->poster_id
                    || $uid === $result[$id]->last_poster_id
                ) {
                    $result[$id]->__dot = true;

                } else {
                    $ids[] = $id;
                }
            }

            if ($ids) {
                $vars = [
                    ':uid' => $uid,
                    ':ids' => $ids,
                ];
                $query = 'SELECT p.topic_id
                    FROM ::posts AS p
                    WHERE p.poster_id=?i:uid AND p.topic_id IN (?ai:ids)
                    GROUP BY p.topic_id';

                $dots = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

            } else {
                $dots = [];
            }
        }

        foreach ($dots as $id) {
            if ($result[$id] instanceof Topic) {
                $result[$id]->__dot = true;
            }
        }

        return $result;
    }
}
