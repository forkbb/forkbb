<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;
use RuntimeException;

class ForumList extends Model
{
    /**
     * Загружает список доступных разделов для текущего пользователя из кеша/БД
     *
     * @return ForumList
     */
    public function init()
    {
        $mark = $this->c->Cache->get('forums_mark');
        if (empty($mark)) {
            $this->c->Cache->set('forums_mark', time());
            return $this->load();
        }

        $result = $this->c->Cache->get('forums_' . $this->c->user->group_id);
        if (empty($result['time']) || $result['time'] < $mark) {
            return $this->load();
        }

        $this->list = $result['list']; //????
        return $this;
    }

    /**
     * Проверяет доступность раздела
     * 
     * @param int $id
     * 
     * @return bool
     */
    public function isAvailable($id)
    {
        return isset($this->list[$id]); //????
    }

    /**
     * 
     * @param int $id
     * 
     * @return null|Forum
     */
    public function forum($id)
    {
        if (isset($this->forums[$id])) {
            return $this->forums[$id];
        } elseif ($this->isAvailable($id)) {
            $forum = $this->c->ModelForum->setAttrs($this->list[$id]);
            $this->a['forums'][$id] = $forum; //????
            return $forum;
        } else {
            return null;
        }
    }
}
