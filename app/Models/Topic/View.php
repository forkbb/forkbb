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
use InvalidArgumentException;
use RuntimeException;

class View extends Action
{
    /**
     * Возвращает список тем
     */
    public function view(mixed $arg): array
    {
        if ($arg instanceof Forum) {
            $full = false;
        } elseif ($arg instanceof Search) {
            $full = true;
        } else {
            throw new InvalidArgumentException('Expected Forum or Search');
        }

        if (
            empty($arg->idsList)
            || ! \is_array($arg->idsList)
        ) {
            throw new RuntimeException('Model does not contain of topics list for display');
        }

        $result = $this->c->topics->loadByIds($arg->idsList, $full);

        if (
            ! $this->c->user->isGuest
            && 1 === $this->c->config->b_show_dot
        ) {
            $vars = [
                ':uid' => $this->c->user->id,
                ':ids' => $arg->idsList,
            ];
            $query = 'SELECT p.topic_id
                FROM ::posts AS p
                WHERE p.poster_id=?i:uid AND p.topic_id IN (?ai:ids)
                GROUP BY p.topic_id';

            $dots = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

            foreach ($dots as $id) {
                if (
                    isset($result[$id])
                    && $result[$id] instanceof Topic
                ) {
                    $result[$id]->__dot = true;
                }
            }
        }

        return $result;
    }
}
