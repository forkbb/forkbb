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
use ForkBB\Models\Topic\Model as Topic;
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
                ':tid' => $this->model->id
            ];
            $query = 'SELECT COUNT(p.id) - 1
                FROM ::posts AS p
                WHERE p.topic_id=?i:tid';

            $numReplies = $this->c->DB->query($query, $vars)->fetchColumn();

            $query = 'SELECT p.id, p.poster, p.poster_id, p.posted
                FROM ::posts AS p
                WHERE p.topic_id=?i:tid
                ORDER BY p.id
                LIMIT 1';

            $result = $this->c->DB->query($query, $vars)->fetch();

            $this->model->poster        = $result['poster'];
            $this->model->poster_id     = $result['poster_id'];
            $this->model->posted        = $result['posted'];
            $this->model->first_post_id = $result['id'];

            $query = 'SELECT p.id, p.poster, p.poster_id, p.posted, p.edited
                FROM ::posts AS p
                WHERE p.topic_id=?i:tid
                ORDER BY p.id DESC
                LIMIT 1';

            $result = $this->c->DB->query($query, $vars)->fetch();

            $this->model->last_post_id   = $result['id'];
            $this->model->last_poster    = $result['poster'];
            $this->model->last_poster_id = $result['poster_id'];
            $this->model->last_post      = $result['edited'] > 0 && $result['edited'] > $result['posted']
                ? $result['edited']
                : $result['posted'];
        }

        $this->model->num_replies = $numReplies;

        return $this->model;
    }
}
