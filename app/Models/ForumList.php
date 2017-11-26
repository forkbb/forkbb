<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;
use RuntimeException;

class ForumList extends Model
{
    /**
     * Заполняет модель данными
     * 
     * @param int $gid
     *
     * @return ForumList
     */
    public function init($gid = 0)
    {
        if (empty($gid)) {
            $gid = $this->c->user->group_id;
        }

        $mark = $this->c->Cache->get('forums_mark');
        if (empty($mark)) {
            $this->c->Cache->set('forums_mark', time());
            $list = $this->refresh($gid);
        } else {
            $result = $this->c->Cache->get('forums_' . $gid);
            if (empty($result['time']) || $result['time'] < $mark) {
                $list = $this->refresh($gid);
            } else {
                $list = $result['list'];
            }
        }

        $this->list = $list; //????
        return $this;
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
        } elseif (isset($this->list[$id])) {
            $forum = $this->c->ModelForum->setAttrs($this->list[$id]);
            $this->a['forums'][$id] = $forum; //????
            return $forum;
        } else {
            return null;
        }
    }
}
