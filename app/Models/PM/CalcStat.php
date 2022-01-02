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
use PDO;
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
        $query = 'SELECT COUNT(pp.id), MAX(pp.id)
            FROM ::pm_posts AS pp
            WHERE pp.topic_id=?i:tid';

        list($count, $maxId) = $this->c->DB->query($query, $vars)->fetch(PDO::FETCH_NUM);

        if (
            empty($count)
            || empty($maxId)
        ) {
            throw new RuntimeException("Bad ptopic: {$this->model->id}");
        }

        $this->model->num_replies = $count - 1;

        $vars = [
            ':id' => $maxId,
        ];
        $query = 'SELECT pp.poster_id, pp.posted, pp.edited
            FROM ::pm_posts AS pp
            WHERE pp.id=?i:id';

        $row = $this->c->DB->query($query, $vars)->fetch();

        $this->model->last_post_id = $maxId;
        $this->model->last_post    = $row['edited'] > $row['posted'] ? $row['edited'] : $row['posted'];

        if ($row['poster_id'] === $this->model->poster_id) {
            $this->model->last_number  = 0;
            $this->model->poster_visit = $this->model->last_post;
        } elseif ($row['poster_id'] === $this->model->target_id) {
            $this->model->last_number  = 1;
            $this->model->target_visit = $this->model->last_post;
        } else {
            throw new RuntimeException("Bad user ID in ppost number {$maxId}");
        }

        return $this->model;
    }
}
