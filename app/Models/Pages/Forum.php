<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Forum extends Page
{
    use ForumsTrait;
    use CrumbTrait;

    /**
     * Подготовка данных для шаблона
     * @param array $args
     * @return Page
     */
    public function view(array $args)
    {
        $this->c->Lang->load('forum');
        $this->c->Lang->load('subforums');

        $forum = $this->c->forums->loadTree($args['id']);
        if (empty($forum)) {
            return $this->c->Message->message('Bad request');
        }

        // редирект, если раздел это ссылка
        if (! empty($forum->redirect_url)) {
            return $this->c->Redirect->url($forum->redirect_url);
        }

        $user = $this->c->user;
        $page = isset($args['page']) ? (int) $args['page'] : 1;
        if (empty($forum->num_topics)) {
            // попытка открыть страницу которой нет
            if ($page !== 1) {
                return $this->c->Message->message('Bad request');
            }

            $pages = 1;
            $offset = 0;
            $topics = null;
        } else {
            $pages = ceil($forum->num_topics / $user->disp_topics);

            // попытка открыть страницу которой нет
            if ($page < 1 || $page > $pages) {
                return $this->c->Message->message('Bad request');
            }

            $offset = ($page - 1) * $user->disp_topics;

            switch ($forum->sort_by) {
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
                ':fid'    => $args['id'],
                ':offset' => $offset,
                ':rows'   => $user->disp_topics,
            ];
            $topics = $this->c->DB
                ->query("SELECT id FROM ::topics WHERE forum_id=?i:fid ORDER BY sticky DESC, {$sortBy}, id DESC LIMIT ?i:offset, ?i:rows", $vars)
                ->fetchAll(\PDO::FETCH_COLUMN);
        }

        if (! empty($topics)) {
            $vars = [
                ':uid'    => $user->id,
                ':topics' => $topics,
            ];

            if (! $user->isGuest && $this->c->config->o_show_dot == '1') {
                $dots = $this->c->DB
                    ->query('SELECT topic_id FROM ::posts WHERE poster_id=?i:uid AND topic_id IN (?ai:topics) GROUP BY topic_id', $vars)
                    ->fetchAll(\PDO::FETCH_COLUMN);
                $dots = array_flip($dots);
            } else {
                $dots = [];
            }

            if (! $user->isGuest) {
                $lower = max((int) $user->u_mark_all_read, (int) $forum->mf_mark_all_read);
                $upper = max($lower, (int) $user->last_visit);
            }

            if ($user->isGuest) {
                $sql = "SELECT id, poster, subject, posted, last_post, last_post_id, last_poster, num_views, num_replies, closed, sticky, moved_to, poll_type FROM ::topics WHERE id IN(?ai:topics) ORDER BY sticky DESC, {$sortBy}, id DESC";
            } else {
                $sql = "SELECT t.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, t.poll_type, mot.mt_last_visit, mot.mt_last_read FROM ::topics AS t LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid) WHERE t.id IN (?ai:topics) ORDER BY t.sticky DESC, t.{$sortBy}, t.id DESC";
            }
            $topics = $this->c->DB->query($sql, $vars)->fetchAll();

            foreach ($topics as &$cur) {
                $cur['subject'] = $this->c->censorship->censor($cur['subject']);
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
                $tPages = ceil(($cur['num_replies'] + 1) / $user->disp_posts);
                if ($tPages > 1) {
                    $cur['pages'] = $this->c->Func->paginate($tPages, -1, 'Topic', ['id' => $cur['id'], 'name' => $cur['subject']]);
                } else {
                    $cur['pages'] = null;
                }

                $cur['link'] = $this->c->Router->link('Topic', ['id' => $cur['id'], 'name' => $cur['subject']]);
                $cur['link_last'] = $this->c->Router->link('ViewPost', ['id' => $cur['last_post_id']]);
                $cur['views'] = $this->c->config->o_topic_views == '1' ? $this->number($cur['num_views']) : null;
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
                    $cur['link_new'] = $this->c->Router->link('TopicViewNew', ['id' => $cur['id']]);
                } else {
                    $cur['link_new'] = null;
                }
                // не прочитанные сообщения
                if ($time > max($lower, (int) $cur['mt_last_read'])) {
                    $cur['link_unread'] = $this->c->Router->link('TopicViewUnread', ['id' => $cur['id']]);
                } else {
                    $cur['link_unread'] = null;
                }
                // активность пользователя в теме
                $cur['dot'] = isset($dots[$cur['id']]);
            }
            unset($cur);
        }

        $moders = empty($forum->moderators) ? [] : array_flip(unserialize($forum->moderators));
        $newOn = $forum->post_topics == 1
            || (null === $forum->post_topics && $user->g_post_topics == 1)
            || $user->isAdmin
            || ($user->isAdmMod && isset($moders[$user->id]));

        $this->fIndex     = 'index';
        $this->nameTpl    = 'forum';
        $this->onlinePos  = 'forum-' . $args['id'];
        $this->canonical  = $this->c->Router->link('Forum', ['id' => $args['id'], 'name' => $forum->forum_name, 'page' => $page]);
        $this->forums     = $this->forumsData($args['id']);
        $this->topics     = $topics;
        $this->crumbs     = $this->crumbs($forum);
        $this->forumName  = $forum->forum_name;
        $this->newTopic   = $newOn ? $this->c->Router->link('NewTopic', ['id' => $args['id']]) : null;
        $this->pages      = $this->c->Func->paginate($pages, $page, 'Forum', ['id' => $args['id'], 'name' => $forum->forum_name]);

        return $this;
    }
}
