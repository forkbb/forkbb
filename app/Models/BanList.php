<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;

class BanList extends Model
{
    /**
     * Загружает список банов из кеша/БД
     *
     * @return BanList
     */
    public function init()
    {
        if ($this->c->Cache->has('banlist')) {
            $list = $this->c->Cache->get('banlist');
            $this->otherList = $list['otherList'];
            $this->userList  = $list['userList'];
            $this->ipList    = $list['ipList'];
        } else {
            $this->load();
        }
        return $this;
    }

    /**
     * Фильтрует значение
     *
     * @param mixed $val
     * @param bool $toLower
     *
     * @return null|string
     */
    public function trimToNull($val, $toLower = false)
    {
        $val = \trim($val);
        if ($val == '') {
            return null;
        } elseif ($toLower) {
            return \mb_strtolower($val, 'UTF-8');
        } else {
            return $val;
        }
    }
}
