<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Topic\Model as Topic;

class Load extends Action
{
    /**
     * Загружает тему из БД
     *
     * @param int $id
     *
     * @return null|Topic
     */
    public function load($id)
    {
        $vars = [
            ':tid' => $id,
            ':uid' => $this->c->user->id,
        ];
        if ($this->c->user->isGuest) {
            $sql = 'SELECT t.*
                    FROM ::topics AS t
                    WHERE t.id=?i:tid';

        } else {
            $sql = 'SELECT t.*, s.user_id AS is_subscribed, mof.mf_mark_all_read, mot.mt_last_visit, mot.mt_last_read
                    FROM ::topics AS t
                    LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid)
                    LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND t.forum_id=mof.fid)
                    LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                    WHERE t.id=?i:tid';
        }

        $data = $this->c->DB->query($sql, $vars)->fetch();

        // тема отсутствует или недоступна
        if (empty($data)) {
            return null;
        }

        $topic = $this->manager->create($data);

        if (! $topic->parent) {
            return null;
        }

        $topic->parent->__mf_mark_all_read = $topic->mf_mark_all_read;

        return $topic;
    }
}
