<?php

namespace ForkBB\Models;

use ForkBB\Models\DataModel;
use ForkBB\Core\Container;
use RuntimeException;

class Topic extends DataModel
{
    protected function getpost_replies()
    {
        if ($this->c->user->isAdmin) {
            return true;
        } elseif ($this->closed || $this->c->user->isBot) {
            return false;
        } elseif ($this->parent->post_replies == '1'
            || (null === $this->parent->post_replies && $this->c->user->g_post_replies == '1')
            || ($this->c->user->isAdmMod && isset($this->parent->moderators[$this->c->user->id]))
        ) {
            return true;
        } else {
            return false;
        }
    }

    protected function getnum_views()
    {
        return $this->c->config->o_topic_views == '1' ? $this->a['num_views'] : null;
    }

    protected function getparent()
    {
        return $this->c->forums->forum($this->forum_id);
    }

    protected function getlink()
    {
        if ($this->moved_to) {
            return $this->c->Router->link('Topic', ['id' => $this->moved_to, 'name' => $this->cens()->subject]);
        } else {
            return $this->c->Router->link('Topic', ['id' => $this->id, 'name' => $this->cens()->subject]);
        }
    }

    protected function getlink_last()
    {
        if ($this->moved_to) {
            return null;
        } else {
            return $this->c->Router->link('ViewPost', ['id' => $this->last_post_id]);
        }
    }

    protected function getlink_new()
    {
        if ($this->c->user->isGuest || $this->moved_to) {
            return null;
        }
        if ($this->last_post > max(
            (int) $this->c->user->u_mark_all_read, 
            (int) $this->parent->mf_mark_all_read,
            (int) $this->c->user->last_visit, 
            (int) $this->mt_last_visit)
        ) {
            return $this->c->Router->link('TopicViewNew', ['id' => $this->id]);
        } else {
            return null;
        }
    }

    protected function getpost_new()
    {
        if ($this->c->user->isGuest || $this->moved_to) {
            return null;
        }
        $upper = max(
            (int) $this->c->user->u_mark_all_read, 
            (int) $this->parent->mf_mark_all_read,
            (int) $this->c->user->last_visit, 
            (int) $this->mt_last_visit
        );
        if ($this->last_post > $upper) {
            $vars = [
                ':tid'   => $this->id,
                ':visit' => $upper,
            ];
            $sql = 'SELECT MIN(id) FROM ::posts WHERE topic_id=?i:tid AND posted>?i:visit';

            $pid = $this->c->DB->query($sql, $vars)->fetchColumn();

            if (! empty($pid)) {
                return $pid;
            }
        }

        return null;
    }

    protected function getlink_unread()
    {
        if ($this->c->user->isGuest || $this->moved_to) {
            return null;
        }
        if ($this->last_post > max(
            (int) $this->c->user->u_mark_all_read, 
            (int) $this->parent->mf_mark_all_read,
            (int) $this->mt_last_read)
        ) {
            return $this->c->Router->link('TopicViewUnread', ['id' => $this->id]);
        } else {
            return null;
        }
    }

    protected function getpost_unread()
    {
        if ($this->c->user->isGuest || $this->moved_to) {
            return null;
        }
        $lower = max(
            (int) $this->c->user->u_mark_all_read, 
            (int) $this->parent->mf_mark_all_read,
            (int) $this->mt_last_read
        );
        if ($this->last_post > $lower) {
            $vars = [
                ':tid'   => $this->id,
                ':visit' => $lower,
            ];
            $sql = 'SELECT MIN(id) FROM ::posts WHERE topic_id=?i:tid AND posted>?i:visit';

            $pid = $this->c->DB->query($sql, $vars)->fetchColumn();

            if (! empty($pid)) {
                return $pid;
            }
        }

        return null;
    }

    protected function getnum_pages()
    {
        if (null === $this->num_replies) {
            throw new RuntimeException('The model does not have the required data');
        }

        return (int) ceil(($this->num_replies + 1) / $this->c->user->disp_posts);
    }

    /**
     * @returm array
     */
    protected function getpages()
    {
        $page = (int) $this->page;
        if ($page < 1 && $this->num_pages === 1) {
            return [];
        } else {
            return $this->c->Func->paginate($this->num_pages, $page, 'Topic', ['id' => $this->id, 'name' => $this->cens()->subject]);
        }
    }

    /**
     * @return bool
     */
    public function hasPage()
    {
        return $this->page > 0 && $this->page <= $this->num_pages;
    }

    /**
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
     * @return array
     */
    public function posts()
    {
        if (! $this->hasPage()) {
            throw new RuntimeException('The model does not have the required data');
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
        return $posts;
    }
}
