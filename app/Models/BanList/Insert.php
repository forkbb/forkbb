<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use ForkBB\Models\BanList\Model as BanList;
use InvalidArgumentException;

class Insert extends Method
{
    /**
     * Добавляет новый бан
     *
     * @param array $ban
     *
     * @return BanList\Model
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

        $sql = 'INSERT INTO ::bans (username, ip, email, message, expire, ban_creator)
                VALUES (?s:username, ?s:ip, ?s:email, ?s:message, ?i:expire, ?i:creator)';
        $this->c->DB->exec($sql, $ban);

        return $this->model;
    }
}
