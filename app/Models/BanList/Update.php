<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Method;
use InvalidArgumentException;

class Update extends Method
{
    /**
     * Обновляет бан
     *
     * @param array $ban
     *
     * @return BanList\Model
     */
    public function update(array $ban)
    {
        if (empty($ban['id'])
            || ! isset($ban['username'])
            || ! isset($ban['ip'])
            || ! isset($ban['email'])
            || ! isset($ban['message'])
            || ! isset($ban['expire'])
        ) {
            throw new InvalidArgumentException('Expected an array with a ban description');
        }

        if ('' == $ban['username'] && '' == $ban['ip'] && '' == $ban['email']) {
            throw new InvalidArgumentException('Empty ban');
        }

        $sql = 'UPDATE ::bans
                SET username=?s:username, ip=?s:ip, email=?s:email, message=?s:message, expire=?i:expire
                WHERE id=?i:id';
        $this->c->DB->exec($sql, $ban);

        return $this->model;
    }
}
