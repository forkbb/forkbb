<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Post\Model as Post;

class UserInfoFromIP extends Action
{
    /**
     * Возвращает массив данных с id пользователей (именами гостей)
     *
     * @param string $ip
     *
     * @return array
     */
    public function userInfoFromIP(string $ip): array
    {
        $vars = [
            ':ip' => $ip,
        ];
        $sql = 'SELECT p.poster_id, p.poster
                FROM ::posts AS p
                WHERE p.poster_ip=?s:ip
                GROUP BY p.poster_id, p.poster
                ORDER BY p.poster';

        $stmt   = $this->c->DB->query($sql, $vars);
        $result = [];
        $ids    = [];

        while ($row = $stmt->fetch()) {
            if ($row['poster_id'] === 1) {
                $result[] = $row['poster'];
            } elseif (empty($ids[$row['poster_id']])) {
                $result[]               = $row['poster_id'];
                $ids[$row['poster_id']] = true;
            }
        }

        return $result;
    }
}
