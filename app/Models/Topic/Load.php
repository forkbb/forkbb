<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\MethodModel;

class Load extends MethodModel
{
    /**
     * Заполняет модель данными из БД
     *
     * @param int $id
     * @param bool $isPost
     *
     * @return Topic
     */
    public function load($id, $isPost = false)
    {
        if ($isPost) {
            $vars = [
                ':pid' => $id,
                ':uid' => $this->c->user->id,
            ];
            if ($this->c->user->isGuest) {
                $sql = 'SELECT t.*, f.moderators
                        FROM ::topics AS t
                        INNER JOIN ::forums AS f ON f.id=t.forum_id
                        INNER JOIN ::posts AS p ON t.id=p.topic_id
                        WHERE p.id=?i:pid';

            } else {
                $sql = 'SELECT t.*, f.moderators, s.user_id AS is_subscribed, mof.mf_mark_all_read, mot.mt_last_visit, mot.mt_last_read
                        FROM ::topics AS t
                        INNER JOIN ::forums AS f ON f.id=t.forum_id
                        INNER JOIN ::posts AS p ON t.id=p.topic_id
                        LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid)
                        LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND f.id=mof.fid)
                        LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                        WHERE p.id=?i:pid';
            }
        } else {
            $vars = [
                ':tid' => $id,
                ':uid' => $this->c->user->id,
            ];
            if ($this->c->user->isGuest) {
                $sql = 'SELECT t.*, f.moderators
                        FROM ::topics AS t
                        INNER JOIN ::forums AS f ON f.id=t.forum_id
                        WHERE t.id=?i:tid';

            } else {
                $sql = 'SELECT t.*, f.moderators, s.user_id AS is_subscribed, mof.mf_mark_all_read, mot.mt_last_visit, mot.mt_last_read
                        FROM ::topics AS t
                        INNER JOIN ::forums AS f ON f.id=t.forum_id
                        LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid)
                        LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND f.id=mof.fid)
                        LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                        WHERE t.id=?i:tid';
            }
        }

        $data = $this->c->DB->query($sql, $vars)->fetch();

        // тема отсутствует или недоступна
        if (empty($data)) {
            return $this->emptyTopic();
        }

        $forForum['moderators'] = $data['moderators'];
        unset($data['moderators']);
        if (! $this->c->user->isGuest) {
            $forForum['mf_mark_all_read'] = $data['mf_mark_all_read'];
            unset($data['mf_mark_all_read']);
        }
        $this->model->setAttrs($data);
        $forum = $this->model->parent;

        // раздел не доступен
        if (empty($forum)) {
            return $this->emptyTopic();
        }

        $forum->replAttrs($forForum);

        return $this->model;
    }

    protected function emptyTopic()
    {
        return $this->model->setAttrs([]);
    }
}
