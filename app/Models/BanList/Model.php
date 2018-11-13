<?php

namespace ForkBB\Models\BanList;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    /**
     * Загружает список банов из кеша/БД
     *
     * @return BanList\Model
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
