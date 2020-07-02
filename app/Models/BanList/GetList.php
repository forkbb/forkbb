<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;

class GetList extends Method
{
    /**
     * Загружает список банов по массиву id
     *
     * @param array $ids
     *
     * @return array
     */
    public function getList(array $ids): array
    {
        $vars  = [
            ':ids' => $ids,
        ];
        $query = 'SELECT b.id, b.username, b.ip, b.email, b.message, b.expire, u.id AS id_creator, u.username AS name_creator
            FROM ::bans AS b
            LEFT JOIN ::users AS u ON u.id=b.ban_creator
            WHERE b.id IN (?ai:ids)';

        $stmt = $this->c->DB->query($query, $vars);
        $list = \array_fill_keys($ids, false);

        while ($row = $stmt->fetch()) {
            $list[$row['id']] = $row;
        }

        return $list;
    }
}
