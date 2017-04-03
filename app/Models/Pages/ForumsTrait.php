<?php

namespace ForkBB\Models\Pages;

trait ForumsTrait 
{
    /**
     * Получение данных по разделам
     * @param int $parent
     * @return array
     */
    protected function getForumsData($parent = 0)
    {
        list($fTree, $fDesc, $fAsc) = $this->c->forums;

        // раздел $parent не имеет подразделов для вывода или они не доступны
        if (empty($fTree[$parent])) {
            return [];
        }

        $user = $this->c->user;

        // текущие данные по подразделам
        $vars = [
            ':id' => $user->id,
            ':forums' => array_slice($fAsc[$parent], 1),
        ];
        if ($user->isGuest) {
            $stmt = $this->c->DB->query('SELECT id, forum_desc, moderators, num_topics, num_posts, last_post, last_post_id, last_poster, last_topic FROM ::forums WHERE id IN (?ai:forums)', $vars);
        } else {
            $stmt = $this->c->DB->query('SELECT f.id, f.forum_desc, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.last_topic, mof.mf_mark_all_read FROM ::forums AS f LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:id AND f.id=mof.fid) WHERE f.id IN (?ai:forums)', $vars);
        }
        $forums = [];
        while ($cur = $stmt->fetch()) {
            $forums[$cur['id']] = $cur;
        }

        // поиск новых
        $new = [];
        if (! $user->isGuest) {
            // предварительная проверка разделов
            $max = max((int) $user->lastVisit, (int) $user->uMarkAllRead);
            foreach ($forums as $id => $cur) {
                $t = max($max, (int) $cur['mf_mark_all_read']);
                if ($cur['last_post'] > $t) {
                    $new[$id] = $t;
                }
            }
            // проверка по темам
            if (! empty($new)) {
                $vars = [
                    ':id' => $user->id,
                    ':forums' => array_keys($new),
                    ':max' => $max,
                ];
                $stmt = $this->c->DB->query('SELECT t.forum_id, t.last_post FROM ::topics AS t LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:id AND mot.tid=t.id) WHERE t.forum_id IN(?ai:forums) AND t.last_post>?i:max AND t.moved_to IS NULL AND (mot.mt_last_visit IS NULL OR t.last_post>mot.mt_last_visit)', $vars);
                $tmp = [];
                while ($cur = $stmt->fetch()) {
                    if ($cur['last_post'] > $new[$cur['forum_id']]) {
                        $tmp[$cur['forum_id']] = true;
                    }
                }
                $new = $tmp;
            }
        }

        $r = $this->c->Router;

        // формированием таблицы разделов
        $result = [];
        foreach ($fTree[$parent] as $fId => $cur) {
            // список подразделов
            $subForums = [];
            if (isset($fTree[$fId])) {
                foreach ($fTree[$fId] as $f) {
                    $subForums[] = [
                        $r->link('Forum', [
                            'id' => $f['fid'],
                            'name' => $f['forum_name']
                        ]),
                        $f['forum_name']
                    ];
                }
            }
            // модераторы
            $moderators = [];
            if (!empty($forums[$fId]['moderators'])) {
                $mods = unserialize($forums[$fId]['moderators']);
                foreach ($mods as $name => $id) {
                    if ($user->gViewUsers == '1') {
                        $moderators[] = [
                            $r->link('User', [
                                'id' => $id,
                                'name' => $name,
                            ]),
                            $name
                        ];
                    } else {
                        $moderators[] = $name;
                    }
                }
            }
            // статистика по разделам
            $numT = 0;
            $numP = 0;
            $time = 0;
            $postId = 0;
            $poster = '';
            $topic = '';
            $fnew = false;
            foreach ($fAsc[$fId] as $id) {
                $fnew = $fnew || isset($new[$id]);
                $numT += $forums[$id]['num_topics'];
                $numP += $forums[$id]['num_posts'];
                if ($forums[$id]['last_post'] > $time) {
                    $time   = $forums[$id]['last_post'];
                    $postId = $forums[$id]['last_post_id'];
                    $poster = $forums[$id]['last_poster'];
                    $topic  = $forums[$id]['last_topic'];
                }
            }

            $result[$cur['cid']]['name'] = $cur['cat_name'];
            $result[$cur['cid']]['forums'][] = [
                'fid'          => $fId,
                'forum_name'   => $cur['forum_name'],
                'forum_desc'   => $forums[$fId]['forum_desc'],
                'forum_link'   => $r->link('Forum', [
                    'id' => $fId,
                    'name' => $cur['forum_name']
                ]),
                'redirect_url' => $cur['redirect_url'],
                'subforums'    => $subForums,
                'moderators'   => $moderators,
                'num_topics'   => $numT,
                'num_posts'    => $numP,
                'topics'       => $this->number($numT),
                'posts'        => $this->number($numP),
                'last_post'    => $this->time($time),
                'last_post_id' => $postId > 0 ? $r->link('ViewPost', ['id' => $postId]) : null,
                'last_poster'  => $poster,
                'last_topic'   => $topic,
                'new'          => $fnew,
            ];
        }
        return $result;
    }
}
