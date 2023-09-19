<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Topic;

use ForkBB\Models\Method;
use ForkBB\Models\Topic\Topic;
use PDO;
use RuntimeException;

class CalcStat extends Method
{
    /**
     * Пересчитывает статистику темы
     */
    public function calcStat(): Topic
    {
        if ($this->model->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        if ($this->model->moved_to) {
            $numReplies = 0;
        } else {
            $vars = [
                ':tid' => $this->model->id,
            ];
            $query = 'SELECT COUNT(p.id), MIN(p.id), MAX(p.id)
                FROM ::posts AS p
                WHERE p.topic_id=?i:tid';

            list($count, $minId, $maxId) = $this->c->DB->query($query, $vars)->fetch(PDO::FETCH_NUM);

            if (
                empty($count)
                || empty($minId)
                || empty($maxId)
            ) {
                throw new RuntimeException("Bad topic: {$this->model->id}");
            }

            $numReplies = $count - 1;

            $vars = [
                ':ids' => [$minId, $maxId],
            ];
            $query = 'SELECT p.id, p.poster, p.poster_id, p.posted, p.edited
                FROM ::posts AS p
                WHERE p.id IN (?ai:ids)';

            $result = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_UNIQUE);

            $row                        = $result[$minId];
            $this->model->first_post_id = $minId;
            $this->model->poster        = $row['poster'];
            $this->model->poster_id     = $row['poster_id'];
            $this->model->posted        = $row['posted'];

            $row                         = $result[$maxId];
            $this->model->last_post_id   = $maxId;
            $this->model->last_poster    = $row['poster'];
            $this->model->last_poster_id = $row['poster_id'];
            $this->model->last_post      = $row['edited'] > $row['posted'] ? $row['edited'] : $row['posted'];
        }

        $this->model->num_replies = $numReplies;

        return $this->model;
    }
}
