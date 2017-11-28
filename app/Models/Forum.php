<?php

namespace ForkBB\Models;

use ForkBB\Models\DataModel;
use ForkBB\Core\Container;
use RuntimeException;
use InvalidArgumentException;

class Forum extends DataModel
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
        if (null === $this->parent_forum_id && $this->id !== 0) {
            throw new RuntimeException('Parent is not defined');
        }

        return $this->c->forums->forum($this->parent_forum_id);
    }

    /**
     * Статус возможности создания новой темы
     * 
     * @return bool
     */
    protected function getcanCreateTopic()
    {
        $user = $this->c->user;
        return $this->post_topics == 1
            || (null === $this->post_topics && $user->g_post_topics == 1)
            || $user->isAdmin
            || ($user->isAdmMod && isset($this->moderators[$user->id]));
    }

    /**
     * Получение массива подразделов
     *
     * @return array
     */
    protected function getsubforums()
    {
        $sub = [];
        if (! empty($this->a['subforums'])) {
            foreach ($this->a['subforums'] as $id) {
                $sub[$id] = $this->c->forums->forum($id);
            }
        }
        return $sub;
    }

    /**
     * Получение массива всех дочерних разделов
     *
     * @return array
     */
    protected function getdescendants()
    {
        $all = [];
        if (! empty($this->a['descendants'])) {
            foreach ($this->a['descendants'] as $id) {
                $all[$id] = $this->c->forums->forum($id);
            }
        }
        return $all;
    }

    /**
     * Ссылка на раздел
     *
     * @return string
     */
    protected function getlink()
    {
        return $this->c->Router->link('Forum', ['id' => $this->id, 'name' => $this->forum_name]);
    }

    /**
     * Ссылка на последнее сообщение в разделе
     *
     * @return null|string
     */
    protected function getlinkLast()
    {
        if ($this->last_post_id < 1) {
            return null;
        } else {
            return $this->c->Router->link('ViewPost', ['id' => $this->last_post_id]);
        }
    }

    /**
     * Ссылка на создание новой темы
     * 
     * @return string
     */
    protected function getlinkCreateTopic()
    {
        return $this->c->Router->link('NewTopic', ['id' => $this->id]);
    }

    /**
     * Получение массива модераторов
     *
     * @return array
     */
    protected function getmoderators()
    {
        if (empty($this->a['moderators'])) {
            return [];
        }

        $moderators = [];
        $mods = unserialize($this->a['moderators']);
        foreach ($mods as $name => $id) {
            if ($this->c->user->g_view_users == '1') {
                $moderators[$id] = [
                    $this->c->Router->link('User', [
                        'id' => $id,
                        'name' => $name,
                    ]),
                    $name
                ];
            } else {
                $moderators[$id] = $name;
            }
        }
        return $moderators;
    }

    /**
     * Возвращает общую статистику по дереву разделов с корнем в текущем разделе
     *
     * @return Models\Forum
     */
    protected function gettree()
    {
        if (empty($this->a['tree'])) { //????
            $numT   = (int) $this->num_topics;
            $numP   = (int) $this->num_posts;
            $time   = (int) $this->last_post;
            $postId = (int) $this->last_post_id;
            $poster = $this->last_poster;
            $topic  = $this->last_topic;
            $fnew   = $this->newMessages;
            foreach ($this->descendants as $chId => $children) {
                $fnew  = $fnew || $children->newMessages;
                $numT += $children->num_topics;
                $numP += $children->num_posts;
                if ($children->last_post > $time) {
                    $time   = $children->last_post;
                    $postId = $children->last_post_id;
                    $poster = $children->last_poster;
                    $topic  = $children->last_topic;
                }
            }
            $this->a['tree'] = $this->c->ModelForum->setAttrs([
                'num_topics'     => $numT,
                'num_posts'      => $numP,
                'last_post'      => $time,
                'last_post_id'   => $postId,
                'last_poster'    => $poster,
                'last_topic'     => $topic,
                'newMessages'    => $fnew,
            ]);
        }
        return $this->a['tree'];
    }

    /**
     * Количество страниц в разделе
     *
     * @throws RuntimeException
     *
     * @return int
     */
    protected function getnumPages()
    {
        if (null === $this->num_topics) {
            throw new RuntimeException('The model does not have the required data');
        }

        return $this->num_topics === 0 ? 1 : (int) ceil($this->num_topics / $this->c->user->disp_topics);
    }

    /**
     * Массив страниц раздела
     *
     * @return array
     */
    protected function getpagination()
    {
        return $this->c->Func->paginate($this->numPages, $this->page, 'Forum', ['id' => $this->id, 'name' => $this->forum_name]);
    }

    /**
     * Статус наличия установленной страницы в разделе
     *
     * @return bool
     */
    public function hasPage()
    {
        return $this->page > 0 && $this->page <= $this->numPages;
    }

    /**
     * Возвращает массив тем с установленной страницы
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function topics()
    {
        if (! $this->hasPage()) {
            throw new InvalidArgumentException('Bad number of displayed page');
        }

        if (empty($this->num_topics)) {
            return [];
        }

        switch ($this->sort_by) {
            case 1:
                $sortBy = 'posted DESC';
                break;
            case 2:
                $sortBy = 'subject ASC';
                break;
            default:
                $sortBy = 'last_post DESC';
                break;
        }

        $vars = [
            ':fid'    => $this->id,
            ':offset' => ($this->page - 1) * $this->c->user->disp_topics,
            ':rows'   => $this->c->user->disp_topics,
        ];
        $sql = "SELECT id
                FROM ::topics
                WHERE forum_id=?i:fid
                ORDER BY sticky DESC, {$sortBy}, id DESC
                LIMIT ?i:offset, ?i:rows";

        $ids = $this->c->DB->query($sql, $vars)->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($ids)) {
            return []; //????
        }

        $vars = [
            ':uid' => $this->c->user->id,
            ':ids' => $ids,
        ];

        if (! $this->c->user->isGuest && $this->c->config->o_show_dot == '1') {
            $dots = $this->c->DB
                ->query('SELECT topic_id FROM ::posts WHERE poster_id=?i:uid AND topic_id IN (?ai:ids) GROUP BY topic_id', $vars)
                ->fetchAll(\PDO::FETCH_COLUMN);
            $dots = array_flip($dots);
        } else {
            $dots = [];
        }

        if ($this->c->user->isGuest) {
            $sql = "SELECT t.*
                    FROM ::topics AS t
                    WHERE t.id IN(?ai:ids)
                    ORDER BY t.sticky DESC, t.{$sortBy}, t.id DESC";
        } else {
            $sql = "SELECT t.*, mot.mt_last_visit, mot.mt_last_read
                    FROM ::topics AS t
                    LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                    WHERE t.id IN (?ai:ids)
                    ORDER BY t.sticky DESC, t.{$sortBy}, t.id DESC";
        }
        $topics = $this->c->DB->query($sql, $vars)->fetchAll();

        foreach ($topics as &$cur) {
            $cur['dot'] = isset($dots[$cur['id']]);
            $cur = $this->c->ModelTopic->setAttrs($cur);
        }
        unset($cur);

        return $topics;
    }
}
