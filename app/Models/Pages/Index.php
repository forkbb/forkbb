<?php

namespace ForkBB\Models\Pages;

class Index extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'index';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = 'index';

    /**
     * Тип обработки пользователей онлайн
     * @var bool
     */
    protected $onlineType = true;

    /**
     * Тип возврата данных при onlineType === true
     * Если true, то из online должны вернутся только пользователи находящиеся на этой же странице
     * Если false, то все пользователи online
     * @var bool
     */
    protected $onlineFilter = false;

    /**
     * Подготовка данных для шаблона
     * @return Page
     */
    public function view()
    {
        $this->c->Lang->load('index');
        $this->c->Lang->load('subforums');

        $user = $this->c->user;
        $r = $this->c->Router;

        $stats = $this->c->users_info;

        $stmt = $this->c->DB->query('SELECT SUM(num_topics), SUM(num_posts) FROM ::forums');
        list($stats['total_topics'], $stats['total_posts']) = array_map([$this, 'number'], array_map('intval', $stmt->fetch(\PDO::FETCH_NUM)));

        $stats['total_users'] = $this->number($stats['total_users']);

        if ($user->gViewUsers == '1') {
            $stats['newest_user'] = [
                $r->link('User', [
                    'id' => $stats['last_user']['id'],
                    'name' => $stats['last_user']['username'],
                ]),
                $stats['last_user']['username']
            ];
        } else {
            $stats['newest_user'] = $stats['last_user']['username'];
        }
        $this->data['stats'] = $stats;

        // вывод информации об онлайн посетителях
        if ($this->config['o_users_online'] == '1') {
            $this->data['online'] = [];
            $this->data['online']['max'] = $this->number($this->config['st_max_users']);
            $this->data['online']['max_time'] = $this->time($this->config['st_max_users_time']);

            // данные онлайн посетителей
            list($users, $guests, $bots) = $this->c->Online->handle($this);
            $list = [];

            if ($user->gViewUsers == '1') {
                foreach ($users as $id => $cur) {
                    $list[] = [
                        $r->link('User', [
                            'id' => $id,
                            'name' => $cur['name'],
                        ]),
                        $cur['name'],
                    ];
                }
            } else {
                foreach ($users as $cur) {
                    $list[] = $cur['name'];
                }
            }
            $this->data['online']['number_of_users'] = $this->number(count($users));

            $s = 0;
            foreach ($bots as $name => $cur) {
                $count = count($cur);
                $s += $count;
                if ($count > 1) {
                    $list[] = '[Bot] ' . $name . ' (' . $count . ')';
                } else {
                    $list[] = '[Bot] ' . $name;
                }
            }
            $s += count($guests);
            $this->data['online']['number_of_guests'] = $this->number($s);
            $this->data['online']['list'] = $list;
        } else {
            $this->onlineType = false;
            $this->c->Online->handle($this);
            $this->data['online'] = null;
        }
        $this->data['forums'] = $this->getForumsData();
        return $this;
    }

    /**
     * Получение данных по разделам
     * @param int $root
     * @return array
     */
    protected function getForumsData($root = 0)
    {
        list($fTree, $fDesc, $fAsc) = $this->c->forums;

        // раздел $root не имеет подразделов для вывода или они не доступны
        if (empty($fTree[$root])) {
            return [];
        }

        $user = $this->c->user;

        // текущие данные по подразделам
        $vars = [
            ':id' => $user->id,
            ':forums' => array_slice($fAsc[$root], 1),
        ];
        if ($user->isGuest) {
            $stmt = $this->c->DB->query('SELECT id, forum_desc, moderators, num_topics, num_posts, last_post, last_post_id, last_poster, last_topic FROM ::forums WHERE id IN (?ai:forums)', $vars);
        } else {
            $stmt = $this->c->DB->query('SELECT f.id, f.forum_desc, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.last_topic, mof.mf_upper FROM ::forums AS f LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:id AND f.id=mof.fid) WHERE f.id IN (?ai:forums)', $vars);
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
                $t = max($max, (int) $cur['mf_upper']);
                if ($cur['last_post'] > $t) {
                    $new[$id] = $t;
                }
            }
            // проверка по темам
            if (! empty($new)) {
                $vars = [
                    ':id' => $user->id,
                    ':forums' => $new,
                    ':max' => $max,
                ];
                $stmt = $this->c->DB->query('SELECT t.forum_id, t.id, t.last_post FROM ::topics AS t LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:id AND mot.tid=t.id) WHERE t.forum_id IN(?ai:forums) AND t.last_post>?i:max AND t.moved_to IS NULL AND (mot.mt_upper IS NULL OR t.last_post>mot.mt_upper)', $vars);
                $tmp = [];
                while ($cur = $stmt->fetch()) {
                    if ($cur['last_post']>$new[$cur['forum_id']]) {
                        $tmp[$cur['forum_id']] = true;
                    }
                }
                $new = $tmp;
            }
        }

        $r = $this->c->Router;

        // формированием таблицы разделов
        $result = [];
        foreach ($fTree[$root] as $fId => $cur) {
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
                'last_post_id' => $postId > 0 ? $r->link('viewPost', ['id' => $postId]) : null,
                'last_poster'  => $poster,
                'last_topic'   => $topic,
                'new'          => $fnew,
            ];
        }
        return $result;
    }
}
