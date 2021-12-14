<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\PM;

use ForkBB\Models\Method;
use ForkBB\Models\PM\PTopic;
use RuntimeException;

class CalcStat extends Method
{
    /**
     * Пересчитывает статистику темы
     */
    public function calcStat(): PTopic
    {
        if ($this->model->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }

        $vars = [
            ':tid' => $this->model->id,
        ];
        $query = 'SELECT pp.id, pp.poster_id, pp.posted, pp.edited
            FROM ::pm_posts AS pp
            WHERE pp.topic_id=?i:tid
            ORDER BY pp.id DESC
            LIMIT 1'; // pp.poster,

        $result = $this->c->DB->query($query, $vars)->fetch();

        $this->model->last_post    = $result['edited'] > $result['posted'] ? $result['edited'] : $result['posted'];
        $this->model->last_post_id = $result['id'];

        if ($result['poster_id'] === $this->model->poster_id) {
            $this->model->last_number  = 0;
            $this->model->poster_visit = $this->model->last_post;
        } elseif ($result['poster_id'] === $this->model->target_id) {
            $this->model->last_number  = 1;
            $this->model->target_visit = $this->model->last_post;
        } else {
            throw new RuntimeException("Bad user ID in ppost number {$result['id']}");
        }

        $query = 'SELECT COUNT(pp.id) - 1
            FROM ::pm_posts AS pp
            WHERE pp.topic_id=?i:tid';

        $this->model->num_replies = (int) $this->c->DB->query($query, $vars)->fetchColumn();

        return $this->model;
    }
}
