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
    public function getList(array $ids)
    {
        $vars = [
            ':ids' => $ids,
        ];
        $sql = 'SELECT b.id, b.username, b.ip, b.email, b.message, b.expire, u.id as id_creator, u.username as name_creator
                LEFT JOIN ::users AS u ON u.id=b.ban_creator
                FROM ::bans AS b
                WHERE id IN (?ai:ids)';

        $this->c->DB->query($sql, $vars);

        $list = \array_fill_keys($ids, false);

        while ($row = $stmt->fetch()) {
            $list[$row['id']] = $row;
        }

        return $list;
    }
}
