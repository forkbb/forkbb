<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use ForkBB\Models\BanList\Model as BanList;
use InvalidArgumentException;

class Update extends Method
{
    /**
     * Обновляет бан
     */
    public function update(array $ban): BanList
    {
        if (
            empty($ban['id'])
            || ! \is_int($ban['id'])
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

        $query = 'UPDATE ::bans
            SET username=?s:username, ip=?s:ip, email=?s:email, message=?s:message, expire=?i:expire
            WHERE id=?i:id';

        $this->c->DB->exec($query, $ban);

        return $this->model;
    }
}
