<?php

namespace ForkBB\Models\Pages;

class Forum extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'forum';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = 'forum';

    /**
     * Подготовка данных для шаблона
     * @param array $args
     * @return Page
     */
    public function view(array $args)
    {
        $this->c->Lang->load('forum');
        $this->c->Lang->load('subforums');

        list($fTree, $fDesc, $fAsc) = $this->c->forums;

        // раздел отсутствует в доступных
        if (empty($fDesc[$args['id']])) {
            return $this->c->Message->message('Bad request');
        }

        $parent = isset($fDesc[$args['id']][0]) ? $fDesc[$args['id']][0] : 0;
        $perm = $fTree[$parent][$args['id']];

        // редирект, если раздел это ссылка
        if (! empty($perm['redirect_url'])) {
            return $this->c->Redirect->setUrl($perm['redirect_url']);
        }
        
        $user = $this->c->user;
        $vars = [
            ':fid' => $args['id'],
            ':uid' => $user->id,
            ':gid' => $user->groupId,
        ];
        if ($user->isGuest) {
            $sql = 'SELECT f.moderators, f.num_topics, f.sort_by, 0 AS is_subscribed FROM ::forums AS f WHERE f.id=?i:fid';
        } else {
            $sql = 'SELECT f.moderators, f.num_topics, f.sort_by, s.user_id AS is_subscribed, mof.mf_mark_all_read FROM ::forums AS f LEFT JOIN ::forum_subscriptions AS s ON (f.id=s.forum_id AND s.user_id=?i:uid) LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND f.id=mof.fid) WHERE f.id=?i:fid';
        }
        $curForum = $this->c->DB->query($sql, $vars)->fetch();

        // нет данных по данному разделу
        if (empty($curForum)) {
            return $this->c->Message->message('Bad request'); //???? может в лог ошибок?
        }

        $page = isset($args['page']) ? (int) $args['page'] : 1;
        if (empty($curForum['num_topics'])) {
            // попытка открыть страницу которой нет
            if ($page !== 1) {
                return $this->c->Message->message('Bad request');
            }

            $pages = 1;
            $offset = 0;
            $topics = null;
        } else {
            $pages = ceil($curForum['num_topics'] / $user->dispTopics);

            // попытка открыть страницу которой нет
            if ($page < 1 || $page > $pages) {
                return $this->c->Message->message('Bad request');
            }

            $offset = $user->dispTopics * ($page - 1);

            switch ($curForum['sort_by']) {
                case 1:
                    $sortBy = 'posted DESC';
                    break;
                case 2:
                    $sortBy = 'subject ASC';
                    break;
                case 0:
                default:
                    $sortBy = 'last_post DESC';
                    break;
            }

            $vars = [
                ':fid' => $args['id'],
                ':offset' => $offset,
                ':rows' => $user->dispTopics,
            ];
            $topics = $this->c->DB
                ->query("SELECT id FROM ::topics WHERE forum_id=?i:fid ORDER BY sticky DESC, {$sortBy}, id DESC LIMIT ?i:offset, ?i:rows", $vars)
                ->fetchAll(\PDO::FETCH_COLUMN);
        }

        if (! empty($topics)) {
            $vars = [
                ':uid' => $user->id,
                ':topics' => $topics,
            ];

            if (! $user->isGuest && $this->config['o_show_dot'] == '1') {
                $dots = $this->c->DB
                    ->query('SELECT topic_id FROM ::posts WHERE poster_id=?i:uid AND topic_id IN (?ai:topics) GROUP BY topic_id', $vars)
                    ->fetchAll(\PDO::FETCH_COLUMN);
                $dots = array_flip($dots);
            } else {
                $dots = [];
            }

            if (! $user->isGuest) {
                $lower = max((int) $user->uMarkAllRead, (int) $curForum['mf_mark_all_read']);
                $upper = max($lower, (int) $user->lastVisit);
            }

            if ($user->isGuest) {
                $sql = "SELECT id, poster, subject, posted, last_post, last_post_id, last_poster, num_views, num_replies, closed, sticky, moved_to, poll_type FROM ::topics WHERE id IN(?ai:topics) ORDER BY sticky DESC, {$sortBy}, id DESC";
            } else {
                $sql = "SELECT t.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, t.poll_type, mot.mt_last_visit, mot.mt_last_read FROM ::topics AS t LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid) WHERE t.id IN (?ai:topics) ORDER BY t.sticky DESC, t.{$sortBy}, t.id DESC";
            }
            $topics = $this->c->DB->query($sql, $vars)->fetchAll();

            foreach ($topics as &$cur) {
                // цензура
                if ($this->config['o_censoring'] == '1') {
                    $cur['subject'] = preg_replace($this->c->censoring[0], $this->c->censoring[1], $cur['subject']);
                }
                // перенос темы
                if ($cur['moved_to']) {
                    $cur['link'] = $this->c->Router->link('Topic', ['id' => $cur['moved_to'], 'name' => $cur['subject']]);
                    $cur['link_last'] = null;
                    $cur['link_new'] = null;
                    $cur['link_unread'] = null;
                    $cur['dot'] = false;
                    continue;
                }
                // страницы темы
                $tPages = ceil(($cur['num_replies'] + 1) / $user->dispPosts);
                if ($tPages > 1) {
                    $cur['pages'] = $this->c->Func->paginate($tPages, -1, 'Topic', ['id' => $cur['id'], 'name' => $cur['subject']]);
                } else {
                    $cur['pages'] = null;
                }

                $cur['link'] = $this->c->Router->link('Topic', ['id' => $cur['id'], 'name' => $cur['subject']]);
                $cur['link_last'] = $this->c->Router->link('ViewPost', ['id' => $cur['last_post_id']]);
                $cur['num_views'] = $this->config['o_topic_views'] == '1' ? $this->number($cur['num_views']) : null;
                $cur['num_replies'] = $this->number($cur['num_replies']);
                $cur['last_post'] = $this->time($cur['last_post']);
                // для гостя пусто
                if ($user->isGuest) {
                    $cur['link_new'] = null;
                    $cur['link_unread'] = null;
                    $cur['dot'] = false;
                    continue;
                }
                // новые сообщения
                if ($cur['last_post'] > max($upper, $cur['mt_last_visit'])) {
                    $cur['link_new'] = $this->c->Router->link('TopicGoToNew', ['id' => $cur['id']]);
                } else {
                    $cur['link_new'] = null;
                }
                // не прочитанные сообщения
                if ($cur['last_post'] > max($lower, $cur['mt_last_read'])) {
                    $cur['link_unread'] = $this->c->Router->link('TopicGoToUnread', ['id' => $cur['id']]);
                } else {
                    $cur['link_unread'] = null;
                }
                // активность пользователя в теме
                $cur['dot'] = isset($dots[$cur['id']]);
            }
            unset($cur);
        }

        $moders = empty($curForum['moderators']) ? [] : array_flip(unserialize($curForum['moderators']));
        $newOn = $perm['post_topics'] == 1 
            || (null === $perm['post_topics'] && $user->gPostTopics == 1)
            || $user->isAdmin 
            || ($user->isAdmMod && isset($moders[$user->id]));

        $this->onlinePos = 'forum-' . $args['id'];
        $crumbs = [];
        $id = $args['id'];
        $activ = true;
        while (true) {
            $name = $fDesc[$id]['forum_name'];
            $this->titles[] = $name;
            $crumbs[] = [
                $this->c->Router->link('Forum', ['id' => $id, 'name' => $name]),
                $name, 
                $activ,
            ];
            $activ = null;
            if (! isset($fDesc[$id][0])) {
                break;
            }
            $id = $fDesc[$id][0];
        }
        $crumbs[] = [
            $this->c->Router->link('Index'),
            __('Index'),
            null,
        ];

        $this->data = [
            'forums' => $this->getForumsData($args['id']),
            'topics' => $topics,
            'crumbs' => array_reverse($crumbs),
            'forumName' => $fDesc[$args['id']]['forum_name'],
            'newTopic' => $newOn ? $this->c->Router->link('NewTopic', ['id' => $args['id']]) : null,
            'pages' => $this->c->Func->paginate($pages, $page, 'Forum', ['id' => $args['id'], 'name' => $fDesc[$args['id']]['forum_name']]),
        ];

        $this->canonical = $page > 1 ? $this->c->Router->link('Forum', ['id' => $args['id'], 'name' => $fDesc[$args['id']]['forum_name'], 'page' => $page])
            : $this->c->Router->link('Forum', ['id' => $args['id'], 'name' => $fDesc[$args['id']]['forum_name']]);
        return $this;
    }

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
            $stmt = $this->c->DB->query('SELECT f.id, f.forum_desc, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.last_topic, mof.mf_last_visit FROM ::forums AS f LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:id AND f.id=mof.fid) WHERE f.id IN (?ai:forums)', $vars);
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
                $t = max($max, (int) $cur['mf_last_visit']);
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
                $stmt = $this->c->DB->query('SELECT t.forum_id, t.id, t.last_post FROM ::topics AS t LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:id AND mot.tid=t.id) WHERE t.forum_id IN(?ai:forums) AND t.last_post>?i:max AND t.moved_to IS NULL AND (mot.mt_last_visit IS NULL OR t.last_post>mot.mt_last_visit)', $vars);
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
