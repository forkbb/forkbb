<?php

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Post\Model as Post;
use PDO;

class UserStat extends Action
{
    /**
     * Возвращает массив данных использования ip для данного пользователя
     */
    public function userStat(int $id): array
    {
        $vars  = [
            ':id' => $id,
        ];
        $query = 'SELECT p.poster_ip, MAX(p.posted) AS last_used, COUNT(p.id) AS used_times
            FROM ::posts AS p
            WHERE p.poster_id=?i:id
            GROUP BY p.poster_ip
            ORDER BY last_used DESC';

        return $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_UNIQUE);
    }
}
