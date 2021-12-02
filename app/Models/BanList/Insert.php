<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use ForkBB\Models\BanList\BanList;
use InvalidArgumentException;

class Insert extends Method
{
    /**
     * Добавляет новый бан
     */
    public function insert(array $ban): BanList
    {
        if (
            isset($ban['id'])
            || ! isset($ban['username'], $ban['ip'], $ban['email'], $ban['message'], $ban['expire'])
        ) {
            throw new InvalidArgumentException('Expected an array with a ban description');
        }

        if (
            '' == $ban['username']
            && '' == $ban['ip']
            && '' == $ban['email']
        ) {
            throw new InvalidArgumentException('Empty ban');
        }

        $ban['creator'] = $this->c->user->id;

        $query = 'INSERT INTO ::bans (username, ip, email, message, expire, ban_creator)
            VALUES (?s:username, ?s:ip, ?s:email, ?s:message, ?i:expire, ?i:creator)';

        $this->c->DB->exec($query, $ban);

        return $this->model;
    }
}
