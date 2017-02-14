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
        $this->c->get('Lang')->load('index');
        $this->c->get('Lang')->load('subforums');

        $db = $this->c->get('DB');
        $user = $this->c->get('user');
        $r = $this->c->get('Router');

        $stats = $this->c->get('users_info');

        $result = $db->query('SELECT SUM(num_topics), SUM(num_posts) FROM '.$db->prefix.'forums') or error('Unable to fetch topic/post count', __FILE__, __LINE__, $db->error());
        list($stats['total_topics'], $stats['total_posts']) = array_map([$this, 'number'], array_map('intval', $db->fetch_row($result)));

        $stats['total_users'] = $this->number($stats['total_users']);

        if ($user['g_view_users'] == '1') {
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
            list($users, $guests, $bots) = $this->c->get('Online')->handle($this);
            $list = [];

            if ($user['g_view_users'] == '1') {
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
            $this->c->get('Online')->handle($this);
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
        list($fTree, $fDesc, $fAsc) = $this->c->get('forums');

        // раздел $root не имеет подразделов для вывода или они не доступны
        if (empty($fTree[$root])) {
            return [];
        }

        $db = $this->c->get('DB');
        $user = $this->c->get('user');

        // текущие данные по подразделам
        $forums = array_slice($fAsc[$root], 1);
        if ($user['is_guest']) {
            $result = $db->query('SELECT id, forum_desc, moderators, num_topics, num_posts, last_post, last_post_id, last_poster, last_topic FROM '.$db->prefix.'forums WHERE id IN ('.implode(',', $forums).')', true) or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());
        } else {
            $result = $db->query('SELECT f.id, f.forum_desc, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.last_topic, mof.mf_upper FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'mark_of_forum AS mof ON (mof.uid='.$user['id'].' AND f.id=mof.fid) WHERE f.id IN ('.implode(',', $forums).')', true) or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());
        }

        $forums = [];
        while ($cur = $db->fetch_assoc($result)) {
            $forums[$cur['id']] = $cur;
        }
        $db->free_result($result);

        // поиск новых
        $new = [];
        if (! $user['is_guest']) {
            // предварительная проверка разделов
            $max = max((int) $user['last_visit'], (int) $user['u_mark_all_read']);
            foreach ($forums as $id => $cur) {
                $t = max($max, (int) $cur['mf_upper']);
                if ($cur['last_post'] > $t) {
                    $new[$id] = $t;
                }
            }
            // проверка по темам
            if (! empty($new)) {
                $result = $db->query('SELECT t.forum_id, t.id, t.last_post FROM '.$db->prefix.'topics AS t LEFT JOIN '.$db->prefix.'mark_of_topic AS mot ON (mot.uid='.$user['id'].' AND mot.tid=t.id) WHERE t.forum_id IN('.implode(',', array_keys($new)).') AND t.last_post>'.$max.' AND t.moved_to IS NULL AND (mot.mt_upper IS NULL OR t.last_post>mot.mt_upper)') or error('Unable to fetch new topics', __FILE__, __LINE__, $db->error());
                $tmp = [];
                while ($cur = $db->fetch_assoc($result)) {
                    if ($cur['last_post']>$new[$cur['forum_id']]) {
                        $tmp[$cur['forum_id']] = true;
                    }
                }
                $new = $tmp;
                $db->free_result($result);
            }
        }

        $r = $this->c->get('Router');

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
                    if ($user['g_view_users'] == '1') {
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
