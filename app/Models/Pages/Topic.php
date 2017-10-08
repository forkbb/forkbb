<?php

namespace ForkBB\Models\Pages;

class Topic extends Page
{
    use UsersTrait;
    use OnlineTrait;
    use CrumbTrait;

    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'topic';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = 'topic';

    /**
     * Тип обработки пользователей онлайн
     * Если false, то идет обновление данных
     * Если true, то идет возврат данных (смотрите $onlineFilter)
     * @var bool
     */
    protected $onlineType = true;

    /**
     * Тип возврата данных при onlineType === true
     * Если true, то из online должны вернутся только пользователи находящиеся на этой же странице
     * Если false, то все пользователи online
     * @var bool
     */
    protected $onlineFilter = true;

    /**
     * Подготовка данных для шаблона
     * @param array $args
     * @return Page
     */
     public function viewNew(array $args)
     {

     }

    /**
     * Подготовка данных для шаблона
     * @param array $args
     * @return Page
     */
     public function viewUnread(array $args)
     {

     }

    /**
     * Переход к последнему сообщению темы
     * @param array $args
     * @return Page
     */
     public function viewLast(array $args)
     {
        $vars = [
            ':tid' => $args['id'],
        ];
        $sql = 'SELECT MAX(id) FROM ::posts WHERE topic_id=?i:tid';

        $pid = $this->c->DB->query($sql, $vars)->fetchColumn();
        // нет ни одного сообщения в теме
        if (empty($pid)) {
            return $this->c->Message->message('Bad request');
        }

        return $this->c->Redirect->setPage('ViewPost', ['id' => $pid]);
     }

     /**
     * Просмотр темы по номеру сообщения
     * @param array $args
     * @return Page
     */
     public function viewPost(array $args)
     {
        $vars = [
            ':pid' => $args['id'],
            ':uid' => $this->c->user->id,
        ];

        if ($this->c->user->isGuest) {
            $sql = 'SELECT t.*, f.moderators, 0 AS is_subscribed, 0 AS mf_mark_all_read, 0 AS mt_last_visit, 0 AS mt_last_read
                    FROM ::topics AS t
                    INNER JOIN ::forums AS f ON f.id=t.forum_id
                    INNER JOIN ::posts AS p ON t.id=p.topic_id
                    WHERE p.id=?i:pid AND t.moved_to IS NULL';
        } else {
            $sql = 'SELECT t.*, f.moderators, s.user_id AS is_subscribed, mof.mf_mark_all_read, mot.mt_last_visit, mot.mt_last_read
                    FROM ::topics AS t
                    INNER JOIN ::forums AS f ON f.id=t.forum_id
                    INNER JOIN ::posts AS p ON t.id=p.topic_id
                    LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid)
                    LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND f.id=mof.fid)
                    LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                    WHERE p.id=?i:pid AND t.moved_to IS NULL';
        }

        return $this->view($sql, $vars, null);
    }

    /**
     * Просмотр темы по ее номеру
     * @param array $args
     * @return Page
     */
    public function viewTopic(array $args)
    {
        $vars = [
            ':tid' => $args['id'],
            ':uid' => $this->c->user->id,
        ];

        if ($this->c->user->isGuest) {
            $sql = 'SELECT t.*, f.moderators, 0 AS is_subscribed, 0 AS mf_mark_all_read, 0 AS mt_last_visit, 0 AS mt_last_read
                    FROM ::topics AS t
                    INNER JOIN ::forums AS f ON f.id=t.forum_id
                    WHERE t.id=?i:tid AND t.moved_to IS NULL';
        } else {
            $sql = 'SELECT t.*, f.moderators, s.user_id AS is_subscribed, mof.mf_mark_all_read, mot.mt_last_visit, mot.mt_last_read
                    FROM ::topics AS t
                    INNER JOIN ::forums AS f ON f.id=t.forum_id
                    LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid)
                    LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND f.id=mof.fid)
                    LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                    WHERE t.id=?i:tid AND t.moved_to IS NULL';
        }

        $page = isset($args['page']) ? (int) $args['page'] : 1;

        return $this->view($sql, $vars, $page);
    }

    /**
     * Подготовка данных для шаблона
     * @param string $sql
     * @param array $vars
     * @param int|null $page
     * @return Page
     */
     protected function view($sql, array $vars, $page)
     {
        $user = $this->c->user;

        $vars[':uid'] = $user->id;

        $topic = $this->c->DB->query($sql, $vars)->fetch();

        // тема отсутствует или недоступна
        if (empty($topic)) {
            return $this->c->Message->message('Bad request');
        }

        list($fTree, $fDesc, $fAsc) = $this->c->forums;

        // раздел отсутствует в доступных
        if (empty($fDesc[$topic['forum_id']])) {
            return $this->c->Message->message('Bad request');
        }

        if (null === $page) {
            $vars[':tid'] = $topic['id'];
            $sql = 'SELECT COUNT(id) FROM ::posts WHERE topic_id=?i:tid AND id<?i:pid';

            $num = 1 + $this->c->DB->query($sql, $vars)->fetchColumn();

            $page = ceil($num / $user->dispPosts);
        }

        $this->c->Lang->load('topic');

        $pages = ceil(($topic['num_replies'] + 1) / $user->dispPosts);
        // попытка открыть страницу которой нет
        if ($page < 1 || $page > $pages) {
            return $this->c->Message->message('Bad request');
        }

        $offset = ($page - 1) * $user->dispPosts;
        $vars = [
            ':tid'    => $topic['id'],
            ':offset' => $offset,
            ':rows'   => $user->dispPosts,
        ];
        $sql = 'SELECT id
                FROM ::posts
                WHERE topic_id=?i:tid
                ORDER BY id LIMIT ?i:offset, ?i:rows';

        $ids = $this->c->DB->query($sql, $vars)->fetchAll(\PDO::FETCH_COLUMN);

        // нарушена синхронизация количества сообщений в темах
        if (empty($ids)) {
            return $this->viewLast($topic['id']);
        }

        $moders = empty($topic['moderators']) ? [] : array_flip(unserialize($topic['moderators']));
        $parent = isset($fDesc[$topic['forum_id']][0]) ? $fDesc[$topic['forum_id']][0] : 0;
        $perm = $fTree[$parent][$topic['forum_id']];

        if($user->isBot) {
            $perm['post_replies'] = 0;
        }

        $newOn = null;
        if ($user->isAdmin) {
            $newOn = true;
        } elseif ($topic['closed'] == '1') {
            $newOn = false;
        } elseif ($perm['post_replies'] === 1
            || (null === $perm['post_replies'] && $user->gPostReplies == '1')
            || ($user->isAdmMod && isset($moders[$user->id]))
        ) {
            $newOn = true;
        }

        // приклейка первого сообщения темы
        $stickFP = (! empty($topic['stick_fp']) || ! empty($topic['poll_type']));
        if ($stickFP) {
            $ids[] = $topic['first_post_id'];
        }

        $vars = [
            ':ids' => $ids,
        ];
        $sql = 'SELECT id, message, poster, posted
                FROM ::warnings
                WHERE id IN (?ai:ids)';

        $warnings = $this->c->DB->query($sql, $vars)->fetchAll(\PDO::FETCH_GROUP);

        $vars = [
            ':ids' => $ids,
        ];
        $sql = 'SELECT u.warning_all, u.gender, u.email, u.title, u.url, u.location, u.signature,
                       u.email_setting, u.num_posts, u.registered, u.admin_note, u.messages_enable,
                       p.id, p.poster as username, p.poster_id, p.poster_ip, p.poster_email, p.message,
                       p.hide_smilies, p.posted, p.edited, p.edited_by, p.edit_post, p.user_agent,
                       g.g_id, g.g_user_title, g.g_promote_next_group, g.g_pm
                FROM ::posts AS p
                INNER JOIN ::users AS u ON u.id=p.poster_id
                INNER JOIN ::groups AS g ON g.g_id=u.group_id
                WHERE p.id IN (?ai:ids) ORDER BY p.id';

        $stmt = $this->c->DB->query($sql, $vars);

        // парсер и его настройка для сообщений
        $bbcodes = include $this->c->DIR_CONFIG . '/defaultBBCode.php';
        $smilies = $this->c->smilies;
        foreach ($smilies as &$cur) {
            $cur = $this->c->PUBLIC_URL . '/img/sm/' . $cur;
        }
        unset($cur);
        $bbInfo = $this->c->BBCODE_INFO;
        $bbWList = $this->config['p_message_bbcode'] == '1' ? null : [];
        $bbBList = $this->config['p_message_img_tag'] == '1' ? [] : ['img'];
        $parser = $this->c->Parser;
        $parser->setBBCodes($bbcodes)
               ->setAttr('isSign', false)
               ->setWhiteList($bbWList)
               ->setBlackList($bbBList);
        if ($user->showSmilies == '1') {
            $parser->setSmilies($smilies)
                   ->setSmTpl($bbInfo['smTpl'], $bbInfo['smTplTag'], $bbInfo['smTplBl']);
        }

        $genders = [1 => ' f-user-male', 2 => ' f-user-female'];
        $postCount = 0;
        $posts = [];
        $signs = [];
        $posters = [];
        $timeMax = 0;
        while ($cur = $stmt->fetch()) {
            // данные по автору сообшения
            if (isset($posters[$cur['poster_id']])) {
                $post = $posters[$cur['poster_id']];
            } else {
                $post = [
                    'poster'            => $cur['username'],
                    'poster_id'         => $cur['poster_id'],
                    'poster_title'      => $this->censor($this->userGetTitle($cur)),
                    'poster_avatar'     => null,
                    'poster_registered' => null,
                    'poster_location'   => null,
                    'poster_info_add'   => false,
                    'poster_link'       => null,
                    'poster_posts'      => null,
                    'poster_gender'     => '',
                    'poster_online'     => '',

                ];
                if ($cur['poster_id'] > 1) {
                    if ($user->gViewUsers == '1') {
                        $post['poster_link'] = $this->c->Router->link('User', ['id' => $cur['poster_id'], 'name' => $cur['username']]);
                    }
                    if ($this->config['o_avatars'] == '1' && $user->showAvatars == '1') {
                        $post['poster_avatar'] = $this->userGetAvatarLink($cur['poster_id']);
                    }
                    if ($this->config['o_show_user_info'] == '1') {
                        $post['poster_info_add'] = true;

                        $post['poster_registered'] = $this->time($cur['registered'], true);

                        $post['poster_posts']     = $this->number($cur['num_posts']);
                        $post['poster_num_posts'] = $cur['num_posts'];

                        if ($cur['location'] != '') {
                            $post['poster_location'] = $this->censor($cur['location']);
                        }
                        if (isset($genders[$cur['gender']])) {
                            $post['poster_gender'] = $genders[$cur['gender']];
                        }
                    }
                    $post['poster_online'] = ' f-user-online'; //????

                    $posters[$cur['poster_id']] = $post;

                    if ($this->config['o_signatures'] == '1'
                        && $cur['signature'] != ''
                        && $user->showSig == '1'
                        && ! isset($signs[$cur['poster_id']])
                    ) {
                        $signs[$cur['poster_id']] = $cur['signature'];
                    }
                }
            }

            // данные по сообщению
            $post['id']         = $cur['id'];
            $post['link']       = $this->c->Router->link('ViewPost', ['id' => $cur['id']]);
            $post['posted']     = $this->time($cur['posted']);
            $post['posted_utc'] = gmdate('Y-m-d\TH:i:s\Z', $cur['posted']);

            $timeMax = max($timeMax, $cur['posted']);

            $parser->parse($this->censor($cur['message']));
            if ($this->config['o_smilies'] == '1' && $user->showSmilies == '1' && $cur['hide_smilies'] == '0') {
                $parser->detectSmilies();
            }
            $post['message'] = $parser->getHtml();

            // номер сообшения в теме
            if ($stickFP && $offset > 0 && $cur['id'] == $topic['first_post_id']) {
                $post['post_number'] = 1;
            } else {
                ++$postCount;
                $post['post_number'] = $offset + $postCount;
            }

            // данные по элементам управления
            $controls = [];
            $vars = ['id' => $cur['id']];
            if (! $user->isAdmin && ! $user->isGuest) {
                $controls['report'] = [$this->c->Router->link('ReportPost', $vars), 'Report'];
            }
            if ($user->isAdmin
                || ($user->isAdmMod && isset($moders[$user->id]) && ! in_array($cur['poster_id'], $this->c->admins))
            ) {
                $controls['delete'] = [$this->c->Router->link('DeletePost', $vars), 'Delete'];
                $controls['edit'] = [$this->c->Router->link('EditPost', $vars), 'Edit'];
            } elseif ($topic['closed'] != '1'
                && $cur['poster_id'] == $user->id
                && ($user->gDeleditInterval == '0' || $cur['edit_post'] == '1' || time() - $cur['posted'] < $user->gDeleditInterval)
            ) {
                if (($cur['id'] == $topic['first_post_id'] && $user->gDeleteTopics == '1') || ($cur['id'] != $topic['first_post_id'] && $user->gDeletePosts == '1')) {
                    $controls['delete'] = [$this->c->Router->link('DeletePost', $vars), 'Delete'];
                }
                if ($user->gEditPosts == '1') {
                    $controls['edit'] = [$this->c->Router->link('EditPost', $vars), 'Edit'];
                }
            }
            if ($newOn) {
                $controls['quote'] = [$this->c->Router->link('NewReply', ['id' => $topic['id'], 'quote' => $cur['id']]), 'Reply'];
            }

            $post['controls'] = $controls;

            $posts[] = $post;
        }

        if ($signs) {
            // настройка парсера для подписей
            $bbWList = $this->config['p_sig_bbcode'] == '1' ? $bbInfo['forSign'] : [];
            $bbBList = $this->config['p_sig_img_tag'] == '1' ? [] : ['img'];
            $parser->setAttr('isSign', true)
                   ->setWhiteList($bbWList)
                   ->setBlackList($bbBList);

            foreach ($signs as &$cur) {
                $parser->parse($this->censor($cur));
                if ($this->config['o_smilies_sig'] == '1' && $user->showSmilies == '1') {
                    $parser->detectSmilies();
                }
                $cur = $parser->getHtml();
            }
            unset($cur);
        }

        $topic['subject'] = $this->censor($topic['subject']);

        $this->onlinePos = 'topic-' . $topic['id'];

        // данные для формы быстрого ответа
        $form = null;
        if ($newOn && $this->config['o_quickpost'] == '1') {
            $form = [
                'action' => $this->c->Router->link('NewReply', ['id' => $topic['id']]),
                'hidden' => [
                    'token' => $this->c->Csrf->create('NewReply', ['id' => $topic['id']]),
                ],
                'sets'   => [],
                'btns'   => [
                    'submit'  => ['submit', __('Submit'), 's'],
                    'preview' => ['submit', __('Preview'), 'p'],
                ],
            ];

            $fieldset = [];
            if ($user->isGuest) {
                $fieldset['username'] = [
                    'dl'        => 't1',
                    'type'      => 'text',
                    'maxlength' => 25,
                    'title'     => __('Username'),
                    'required'  => true,
                    'pattern'   => '^.{2,25}$',
                ];
                $fieldset['email'] = [
                    'dl'        => 't2',
                    'type'      => 'text',
                    'maxlength' => 80,
                    'title'     => __('Email'),
                    'required'  => $this->config['p_force_guest_email'] == '1',
                    'pattern'   => '.+@.+',
                ];
            }

            $fieldset['message'] = [
                'type'     => 'textarea',
                'title'    => __('Message'),
                'required' => true,
                'bb'       => [
                    ['link', __('BBCode'), __($this->config['p_message_bbcode'] == '1' ? 'on' : 'off')],
                    ['link', __('url tag'), __($this->config['p_message_bbcode'] == '1' && $user->gPostLinks == '1' ? 'on' : 'off')],
                    ['link', __('img tag'), __($this->config['p_message_bbcode'] == '1' && $this->config['p_message_img_tag'] == '1' ? 'on' : 'off')],
                    ['link', __('Smilies'), __($this->config['o_smilies'] == '1' ? 'on' : 'off')],
                ],
            ];
            $form['sets'][] = [
                'fields' => $fieldset,
            ];

            $fieldset = [];
            if ($user->isAdmin || ($user->isAdmMod && isset($moders[$user->id]))) {
                $fieldset['merge'] = [
                    'type'    => 'checkbox',
                    'label'   => __('Merge posts'),
                    'value'   => '1',
                    'checked' => true,
                ];
            }
            if ($fieldset) {
                $form['sets'][] = [
                    'legend' => __('Options'),
                    'fields' => $fieldset,
                ];
            }
        }

        $this->data = [
            'topic'    => $topic,
            'posts'    => $posts,
            'signs'    => $signs,
            'warnings' => $warnings,
            'crumbs'   => $this->getCrumbs(
                ['Topic', ['id' => $topic['id'], 'name' => $topic['subject']]],
                [$fDesc, $topic['forum_id']]
            ),
            'NewReply' => $newOn ? $this->c->Router->link('NewReply', ['id' => $topic['id']]) : $newOn,
            'stickFP'  => $stickFP,
            'pages'    => $this->c->Func->paginate($pages, $page, 'Topic', ['id' => $topic['id'], 'name' => $topic['subject']]),
            'online'   => $this->getUsersOnlineInfo(),
            'stats'    => null,
            'form'     => $form,
        ];

        $this->canonical = $this->c->Router->link('Topic', ['id' => $topic['id'], 'name' => $topic['subject'], 'page' => $page]);

        if ($this->config['o_topic_views'] == '1') {
            $vars = [
                ':tid' => $topic['id'],
            ];
            $sql = 'UPDATE ::topics SET num_views=num_views+1 WHERE id=?i:tid';

            $this->c->DB->query($sql, $vars);
        }

        if (! $user->isGuest) {
            $vars = [
                ':uid'   => $user->id,
                ':tid'   => $topic['id'],
                ':read'  => $topic['mt_last_read'],
                ':visit' => $topic['mt_last_visit'],
            ];
            $flag = false;
            $lower = max((int) $user->uMarkAllRead, (int) $topic['mf_mark_all_read'], (int) $topic['mt_last_read']); //????
            if ($timeMax > $lower) {
                $vars[':read'] = $timeMax;
                $flag = true;
            }
            $upper = max($lower, (int) $topic['mt_last_visit'], (int) $user->lastVisit); //????
            if ($topic['last_post'] > $upper) {
                $vars[':visit'] = $topic['last_post'];
                $flag = true;
            }
            if ($flag) {
                if (empty($topic['mt_last_read']) && empty($topic['mt_last_visit'])) {
                    $this->c->DB->exec('INSERT INTO ::mark_of_topic (uid, tid, mt_last_visit, mt_last_read) 
                                        SELECT ?i:uid, ?i:tid, ?i:visit, ?i:read 
                                        FROM ::groups 
                                        WHERE NOT EXISTS (SELECT 1 
                                                          FROM ::mark_of_topic 
                                                          WHERE uid=?i:uid AND tid=?i:tid) 
                                        LIMIT 1', $vars);
                } else {
                    $this->c->DB->exec('UPDATE ::mark_of_topic 
                    SET mt_last_visit=?i:visit, mt_last_read=?i:read 
                    WHERE uid=?i:uid AND tid=?i:tid', $vars);
                }
            }
        }

        return $this;
    }
}
