<?php

namespace ForkBB\Models\Pages;

class Topic extends Page
{
    use UsersTrait;

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
     * Подготовка данных для шаблона
     * @param array $args
     * @return Page
     */
     public function goToNew(array $args)
     {

     }

    /**
     * Подготовка данных для шаблона
     * @param array $args
     * @return Page
     */
     public function goToUnread(array $args)
     {
         
     }

    /**
     * Переход к последнему сообщению темы
     * @param array $args
     * @return Page
     */
     public function goToLast(array $args)
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
        ];
        $sql = 'SELECT topic_id FROM ::posts WHERE id=?i:pid';

        $tid = $this->c->DB->query($sql, $vars)->fetchColumn();
        // сообшение не найдено в базе
        if (empty($tid)) {
            return $this->c->Message->message('Bad request');
        }

        $vars = [
            ':pid' => $args['id'],
            ':tid' => $tid,
        ];
        $sql = 'SELECT COUNT(id) FROM ::posts WHERE topic_id=?i:tid AND id<?i:pid';

        $num = 1 + $this->c->DB->query($sql, $vars)->fetchColumn();

        return $this->view([
            'id' => $tid,
            'page' => ceil($num / $this->c->user->dispPosts),
        ]);
    }

    /**
     * Подготовка данных для шаблона
     * @param array $args
     * @return Page
     */
    public function view(array $args)
    {
        $this->c->Lang->load('topic');

        $user = $this->c->user;
        $vars = [
            ':tid' => $args['id'],
            ':uid' => $user->id,
        ];

        if ($user->isGuest) {
            $sql = 'SELECT t.*, f.moderators, 0 AS is_subscribed 
                    FROM ::topics AS t 
                    INNER JOIN ::forums AS f ON f.id=t.forum_id 
                    WHERE t.id=?i:tid AND t.moved_to IS NULL';
        } else {
            $sql = 'SELECT t.*, f.moderators, s.user_id AS is_subscribed 
                    FROM ::topics AS t 
                    INNER JOIN ::forums AS f ON f.id=t.forum_id 
                    LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid) 
                    WHERE t.id=?i:tid AND t.moved_to IS NULL';
        }
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

        $page = isset($args['page']) ? (int) $args['page'] : 1;
        $pages = ceil(( $topic['num_replies'] + 1) / $user->dispPosts);

        // попытка открыть страницу которой нет
        if ($page < 1 || $page > $pages) {
            return $this->c->Message->message('Bad request');
        }

        $offset = ($page - 1) * $user->dispPosts;
        
        $vars = [
            ':tid' => $args['id'],
            ':offset' => $offset,
            ':rows' => $user->dispPosts,
        ];
        $sql = 'SELECT id 
                FROM ::posts 
                WHERE topic_id=?i:tid 
                ORDER BY id LIMIT ?i:offset, ?i:rows';
        $ids = $this->c->DB->query($sql, $vars)->fetchAll(\PDO::FETCH_COLUMN);

        // нарушена синхронизация количества сообщений в темах
        if (empty($ids)) {
            return $this->goToLast($args); //????
        }

        $moders = empty($topic['moderators']) ? [] : array_flip(unserialize($topic['moderators']));

        $parent = isset($fDesc[$topic['forum_id']][0]) ? $fDesc[$topic['forum_id']][0] : 0;
        $perm = $fTree[$parent][$topic['forum_id']];

        if ($user->isAdmin) {
            $newPost = $this->c->Router->link('NewPost', ['id' => $args['id']]);
        } elseif ($topic['closed'] == '1') {
            $newPost = false;
        } elseif ($perm['post_replies'] === 1 
            || (null === $perm['post_replies'] && $user->gPostReplies == '1')
            || ($user->isAdmMod && isset($moders[$user->id]))
        ) {
            $newPost = $this->c->Router->link('NewPost', ['id' => $args['id']]);
        } else {
            $newPost = null;
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

        $genders = [1 => ' f-user-male', 2 => ' f-user-female'];
        $postCount = 0;
        $posts = [];
        $posters = [];
        while ($cur = $stmt->fetch()) {
            // данные по автору сообшения
            if (isset($posters[$cur['poster_id']])) {
                $post = $posters[$cur['poster_id']];
            } else {
                $post = [
                    'poster'            => $cur['username'],
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
                }
            }

            // данные по сообщению
            $post['id']         = $cur['id'];
            $post['link']       = $this->c->Router->link('ViewPost', ['id' => $cur['id']]);
            $post['posted']     = $this->time($cur['posted']);
            $post['posted_utc'] = gmdate('Y-m-d\TH:i:s\Z', $cur['posted']);

            // номер сообшения в теме
            if ($stickFP && $offset > 0 && $cur['id'] == $topic['first_post_id']) {
                $post['post_number'] = 1;
            } else {
                ++$postCount;
                $post['post_number'] = $offset + $postCount;
            }

            // данные по элементам управления
            $controls = [];
            if (! $user->isAdmin && ! $user->isGuest) {
                $controls['report'] = ['#', 'Report'];
            }
            if ($user->isAdmin 
                || ($user->isAdmMod && isset($moders[$user->id]))
                || ($cur['poster_id'] == $user->id) //????
            ) {
                $controls['edit'] = ['#', 'Edit'];
            }
            if ($newPost) {
                $controls['quote'] = ['#', 'Reply'];
            }

            $post['controls'] = $controls;
            
            $posts[] = $post;
        }

        $topic['subject'] = $this->censor($topic['subject']);
        
        $crumbs = [];
        $crumbs[] = [
            $this->c->Router->link('Topic', ['id' => $args['id'], 'name' => $topic['subject']]),
            $topic['subject'],
            true,
        ];
        $this->titles[] = $topic['subject'];

        $id = $topic['forum_id'];
        $activ = null;
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
            'topic' => $topic,
            'posts' => $posts,
            'warnings' => $warnings,
            'crumbs' => array_reverse($crumbs),
            'topicName' => $topic['subject'],
            'newPost' => $newPost,
            'stickFP' => $stickFP,
            'pages' => $this->c->Func->paginate($pages, $page, 'Topic', ['id' => $args['id'], 'name' => $topic['subject']]),
        ];

        $this->canonical = $this->c->Router->link('Topic', ['id' => $args['id'], 'name' => $topic['subject'], 'page' => $page]);

        return $this;
    }
}
