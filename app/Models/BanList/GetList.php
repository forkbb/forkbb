<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;

class GetList extends Method
{
    /**
     * Загружает список банов по массиву id
     */
    public function getList(array $ids): array
    {
        $vars = [
            ':ids' => $ids,
        ];
        $query = 'SELECT b.id, b.username, b.ip, b.email, b.message, b.expire, b.ban_creator AS id_creator, u.username AS name_creator
            FROM ::bans AS b
            LEFT JOIN ::users AS u ON u.id=b.ban_creator
            WHERE b.id IN (?ai:ids)';

        $stmt = $this->c->DB->query($query, $vars);
        $list = \array_fill_keys($ids, false);

        while ($row = $stmt->fetch()) {
            if (null === $row['name_creator']) {
                $row['name_creator'] = 'User #' . $row['id_creator'];
                $row['id_creator']   = 0;
            }

            $list[$row['id']] = $row;
        }

        return $list;
    }
}
