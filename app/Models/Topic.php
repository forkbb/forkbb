<?php

namespace ForkBB\Models;

use ForkBB\Models\DataModel;
use ForkBB\Core\Container;
use RuntimeException;

class Topic extends DataModel
{
    /**
     * Получение родительского раздела
     *
     * @throws RuntimeException
     *
     * @return Models\Forum
     */
    protected function getparent()
    {
        if ($this->forum_id < 1) {
            throw new RuntimeException('Parent is not defined');
        }

        return $this->c->forums->forum($this->forum_id);
    }

    /**
     * Статус возможности ответа в теме
     *
     * @return bool
     */
    protected function getcanReply()
    {
        if ($this->c->user->isAdmin) {
            return true;
        } elseif ($this->closed || $this->c->user->isBot) {
            return false;
        } elseif ($this->parent->post_replies == '1'
            || (null === $this->parent->post_replies && $this->c->user->g_post_replies == '1')
            || $this->c->user->isModerator($this)
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Ссылка на тему
     *
     * @return string
     */
    protected function getlink()
    {
        return $this->c->Router->link('Topic', ['id' => $this->moved_to ?: $this->id, 'name' => \ForkBB\cens($this->subject)]);
    }

    /**
     * Ссылка для ответа в теме
     *
     * @return string
     */
    protected function getlinkReply()
    {
        return $this->c->Router->link('NewReply', ['id' => $this->id]);
    }

    /**
     * Ссылка для перехода на последнее сообщение темы
     *
     * @return null|string
     */
    protected function getlinkLast()
    {
        if ($this->moved_to) {
            return null;
        } else {
            return $this->c->Router->link('ViewPost', ['id' => $this->last_post_id]);
        }
    }

    /**
     * Ссылка для перехода на первое новое сообщение в теме
     *
     * @return string
     */
    protected function getlinkNew()
    {
        return $this->c->Router->link('TopicViewNew', ['id' => $this->id]);
    }

    /**
     * Ссылка для перехода на первое не прочитанное сообщение в теме
     */
    protected function getlinkUnread()
    {
        return $this->c->Router->link('TopicViewUnread', ['id' => $this->id]);
    }

    /**
     * Статус наличия новых сообщений в теме
     *
     * @return false|int
     */
    protected function gethasNew()
    {
        if ($this->c->user->isGuest || $this->moved_to) {
            return false;
        }

        $time = max(
            (int) $this->c->user->u_mark_all_read,
            (int) $this->parent->mf_mark_all_read,
            (int) $this->c->user->last_visit,
            (int) $this->mt_last_visit
        );

        return $this->last_post > $time ? $time : false;
    }

    /**
     * Статус наличия не прочитанных сообщений в теме
     *
     * @return false|int
     */
    protected function gethasUnread()
    {
        if ($this->c->user->isGuest || $this->moved_to) {
            return false;
        }

        $time = max(
            (int) $this->c->user->u_mark_all_read,
            (int) $this->parent->mf_mark_all_read,
            (int) $this->mt_last_read
        );

        return $this->last_post > $time ? $time : false;
    }

    /**
     * Номер первого нового сообщения в теме
     *
     * @return int
     */
    protected function getfirstNew()
    {
        if (false === $this->hasNew) {
            return 0;
        }

        $vars = [
            ':tid'   => $this->id,
            ':visit' => $this->hasNew,
        ];
        $sql = 'SELECT MIN(id) FROM ::posts WHERE topic_id=?i:tid AND posted>?i:visit';

        $pid = $this->c->DB->query($sql, $vars)->fetchColumn();

        return $pid ?: 0;
    }

    /**
     * Номер первого не прочитанного сообщения в теме
     *
     * @return int
     */
    protected function getfirstUnread()
    {
        if (false === $this->hasUnread) {
            return 0;
        }

        $vars = [
            ':tid'   => $this->id,
            ':visit' => $this->hasUnread,
        ];
        $sql = 'SELECT MIN(id) FROM ::posts WHERE topic_id=?i:tid AND posted>?i:visit';

        $pid = $this->c->DB->query($sql, $vars)->fetchColumn();

        return $pid ?: 0;
    }

    /**
     * Количество страниц в теме
     *
     * @throws RuntimeException
     *
     * @return int
     */
    protected function getnumPages()
    {
        if (null === $this->num_replies) {
            throw new RuntimeException('The model does not have the required data');
        }

        return (int) ceil(($this->num_replies + 1) / $this->c->user->disp_posts);
    }

    /**
     * Массив страниц темы
     *
     * @return array
     */
    protected function getpagination()
    {
        $page = (int) $this->page;

        if ($page < 1 && $this->numPages === 1) {
            // 1 страницу в списке тем раздела не отображаем
            return [];
        } else { //????
            return $this->c->Func->paginate($this->numPages, $page, 'Topic', ['id' => $this->id, 'name' => \ForkBB\cens($this->subject)]);
        }
    }

    /**
     * Статус наличия установленной страницы в теме
     *
     * @return bool
     */
    public function hasPage()
    {
        return $this->page > 0 && $this->page <= $this->numPages;
    }

    /**
     * Вычисляет страницу темы на которой находится данное сообщение
     *
     * @param int $pid
     */
    public function calcPage($pid)
    {
        $vars = [
            ':tid' => $this->id,
            ':pid' => $pid,
        ];
        $sql = 'SELECT COUNT(p.id) AS num, j.id AS flag
                FROM ::posts AS p
                INNER JOIN ::posts AS j ON (j.topic_id=?i:tid AND j.id=?i:pid)
                WHERE p.topic_id=?i:tid AND p.id<?i:pid';

        $result = $this->c->DB->query($sql, $vars)->fetch();

        $this->page = empty($result['flag']) ? null : (int) ceil(($result['num'] + 1) / $this->c->user->disp_posts);
    }

    /**
     * Возвращает массив сообщений с установленной рание страницы темы
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function posts()
    {
        if (! $this->hasPage()) {
            throw new InvalidArgumentException('Bad number of displayed page');
        }

        $offset = ($this->page - 1) * $this->c->user->disp_posts;
        $vars = [
            ':tid'    => $this->id,
            ':offset' => $offset,
            ':rows'   => $this->c->user->disp_posts,
        ];
        $sql = 'SELECT id
                FROM ::posts
                WHERE topic_id=?i:tid
                ORDER BY id LIMIT ?i:offset, ?i:rows';

        $ids = $this->c->DB->query($sql, $vars)->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($ids)) {
            return [];
        }

        // приклейка первого сообщения темы
        if ($this->stick_fp || $this->poll_type) {
            $ids[] = $this->first_post_id;
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
                       u.group_id,
                       p.id, p.poster as username, p.poster_id, p.poster_ip, p.poster_email, p.message,
                       p.hide_smilies, p.posted, p.edited, p.edited_by, p.edit_post, p.user_agent,
                       g.g_user_title, g.g_promote_next_group, g.g_pm
                FROM ::posts AS p
                INNER JOIN ::users AS u ON u.id=p.poster_id
                INNER JOIN ::groups AS g ON g.g_id=u.group_id
                WHERE p.id IN (?ai:ids) ORDER BY p.id';

        $posts = $this->c->DB->query($sql, $vars)->fetchAll();

        $postCount = 0;
        $timeMax = 0;

        foreach ($posts as &$cur) {
            if ($cur['posted'] > $timeMax) {
                $timeMax = $cur['posted'];
            }

            // номер сообшения в теме
            if ($cur['id'] == $this->first_post_id && $offset > 0) {
                $cur['postNumber'] = 1;
            } else {
                ++$postCount;
                $cur['postNumber'] = $offset + $postCount;
            }

            if (isset($warnings[$cur['id']])) {
                $cur['warnings'] = $warnings[$cur['id']];
            }

            $cur['parent'] = $this;

            $cur = $this->c->ModelPost->setAttrs($cur);
        }
        unset($cur);
        $this->timeMax = $timeMax;
        return $posts;
    }

    /**
     * Статус показа/подсчета просмотров темы
     *
     * @return bool
     */
    protected function getshowViews()
    {
        return $this->c->config->o_topic_views == '1';
    }

    /**
     * Увеличивает на 1 количество просмотров темы
     */
    public function incViews()
    {
        $vars = [
            ':tid' => $this->id,
        ];
        $sql = 'UPDATE ::topics SET num_views=num_views+1 WHERE id=?i:tid';

        $this->c->DB->query($sql, $vars);
    }

    /**
     * Обновление меток последнего визита и последнего прочитанного сообщения
     */
    public function updateVisits()
    {
        if ($this->c->user->isGuest) {
            return;
        }

        $vars = [
            ':uid'   => $this->c->user->id,
            ':tid'   => $this->id,
            ':read'  => $this->mt_last_read,
            ':visit' => $this->mt_last_visit,
        ];
        $flag = false;

        if (false !== $this->hasNew) {
            $flag = true;
            $vars[':visit'] = $this->last_post;
        }
        if (false !== $this->hasUnread && $this->timeMax > $this->hasUnread) {
            $flag = true;
            $vars[':read'] = $this->timeMax;
        }

        if ($flag) {
            if (empty($this->mt_last_read) && empty($this->mt_last_visit)) {
                $sql = 'INSERT INTO ::mark_of_topic (uid, tid, mt_last_visit, mt_last_read)
                        SELECT ?i:uid, ?i:tid, ?i:visit, ?i:read
                        FROM ::groups
                        WHERE NOT EXISTS (SELECT 1
                                          FROM ::mark_of_topic
                                          WHERE uid=?i:uid AND tid=?i:tid)
                        LIMIT 1';
            } else {
                $sql = 'UPDATE ::mark_of_topic
                        SET mt_last_visit=?i:visit, mt_last_read=?i:read
                        WHERE uid=?i:uid AND tid=?i:tid';
            }
            $this->c->DB->exec($sql, $vars);
        }
    }
}
