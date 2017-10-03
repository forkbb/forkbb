<?php

namespace ForkBB\Models\Pages;

class Forum extends Page
{
    use ForumsTrait;
    use CrumbTrait;

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

            $offset = ($page - 1) * $user->dispTopics;

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
                $cur['subject'] = $this->censor($cur['subject']);
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
                $cur['views'] = $this->config['o_topic_views'] == '1' ? $this->number($cur['num_views']) : null;
                $cur['replies'] = $this->number($cur['num_replies']);
                $time = $cur['last_post'];
                $cur['last_post'] = $this->time($cur['last_post']);
                // для гостя пусто
                if ($user->isGuest) {
                    $cur['link_new'] = null;
                    $cur['link_unread'] = null;
                    $cur['dot'] = false;
                    continue;
                }
                // новые сообщения
                if ($time > max($upper, (int) $cur['mt_last_visit'])) {
                    $cur['link_new'] = $this->c->Router->link('TopicGoToNew', ['id' => $cur['id']]);
                } else {
                    $cur['link_new'] = null;
                }
                // не прочитанные сообщения
                if ($time > max($lower, (int) $cur['mt_last_read'])) {
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

        $this->data = [
            'forums' => $this->getForumsData($args['id']),
            'topics' => $topics,
            'crumbs' => $this->getCrumbs([$fDesc, $args['id']]),
            'forumName' => $fDesc[$args['id']]['forum_name'],
            'newTopic' => $newOn ? $this->c->Router->link('NewTopic', ['id' => $args['id']]) : null,
            'pages' => $this->c->Func->paginate($pages, $page, 'Forum', ['id' => $args['id'], 'name' => $fDesc[$args['id']]['forum_name']]),
        ];

        $this->canonical = $this->c->Router->link('Forum', ['id' => $args['id'], 'name' => $fDesc[$args['id']]['forum_name'], 'page' => $page]);

        return $this;
    }
}
