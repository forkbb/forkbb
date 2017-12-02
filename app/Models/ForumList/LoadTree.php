<?php

namespace ForkBB\Models\ForumList;

use ForkBB\Models\MethodModel;

class LoadTree extends MethodModel
{
    /**
     * Загружает данные в модели для указанного раздела и всех его потомков
     * 
     * @param int $rootId
     * 
     * @return null|Forum
     */
    public function loadTree($rootId)
    {
        $root = $this->model->forum($rootId);
        if (null === $root) {
            return null;
        }

        $list = [];
        if (! $root->ready) {
            $list[$rootId] = $root;
        }
        foreach ($root->descendants as $id => $descendant) {
            if (! $descendant->ready) {
                $list[$id] = $descendant;
            }
        }

        $this->loadData($list);

        if (! $this->c->user->isGuest) {
            $this->checkForNew($root->descendants);
        }

        return $root;
    }

    /**
     * Загружает данные из БД по списку разделов
     * 
     * @param array $list
     */
    public function loadData(array $list)
    {
        if (empty($list)) {
            return;
        }

        $vars = [
            ':uid'    => $this->c->user->id,
            ':forums' => array_keys($list),
        ];

        if ($this->c->user->isGuest) {
            $sql = 'SELECT f.id, f.forum_desc, f.num_topics, f.sort_by, f.num_posts,
                           f.last_post, f.last_post_id, f.last_poster, f.last_topic
                    FROM ::forums AS f
                    WHERE id IN (?ai:forums)';
        } elseif ($this->c->config->o_forum_subscriptions == '1') {
            $sql = 'SELECT f.id, f.forum_desc, f.num_topics, f.sort_by, f.num_posts,
                           f.last_post, f.last_post_id, f.last_poster, f.last_topic,
                           mof.mf_mark_all_read, s.user_id AS is_subscribed
                    FROM ::forums AS f 
                    LEFT JOIN ::forum_subscriptions AS s ON (s.user_id=?i:uid AND s.forum_id=f.id) 
                    LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND mof.fid=f.id)
                    WHERE f.id IN (?ai:forums)';
        } else {
            $sql = 'SELECT f.id, f.forum_desc, f.num_topics, f.sort_by, f.num_posts,
                           f.last_post, f.last_post_id, f.last_poster, f.last_topic,
                           mof.mf_mark_all_read 
                    FROM ::forums AS f 
                    LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:id AND mof.fid=f.id)
                    WHERE f.id IN (?ai:forums)';
        }

        $stmt = $this->c->DB->query($sql, $vars);
        while ($cur = $stmt->fetch()) {
            $list[$cur['id']]->replAttrs($cur)->__ready = true;
        }
    }

    /**
     * Проверяет наличие новых сообщений в разделах по их списку
     * 
     * @param array $list
     */
    protected function checkForNew(array $list)
    {
        if (empty($list) || $this->c->user->isGuest) {
            return;
        }

        // предварительная проверка разделов
        $time = [];
        $max = max((int) $this->c->user->last_visit, (int) $this->c->user->u_mark_all_read);
        foreach ($list as $forum) {
            $t = max($max, (int) $forum->mf_mark_all_read);
            if ($forum->last_post > $t) {
                $time[$forum->id] = $t;
            }
        }

        if (empty($time)) {
            return;
        }

        // проверка по темам
        $vars = [
            ':uid'    => $this->c->user->id,
            ':forums' => array_keys($time),
            ':max'    => $max,
        ];
        $sql = 'SELECT t.forum_id, t.last_post 
                FROM ::topics AS t 
                LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND mot.tid=t.id) 
                WHERE t.forum_id IN(?ai:forums) 
                    AND t.last_post>?i:max 
                    AND t.moved_to IS NULL 
                    AND (mot.mt_last_visit IS NULL OR t.last_post>mot.mt_last_visit)';
        $stmt = $this->c->DB->query($sql, $vars);
        while ($cur = $stmt->fetch()) {
            if ($cur['last_post'] > $time[$cur['forum_id']]) {
                $list[$cur['forum_id']]->__newMessages = true; //????
            }
        }
    }
}
