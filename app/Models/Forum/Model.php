<?php

namespace ForkBB\Models\Forum;

use ForkBB\Models\DataModel;
use ForkBB\Models\User\Model as User;
use RuntimeException;
use InvalidArgumentException;
use PDO;

class Model extends DataModel
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

        return $this->c->forums->get($this->parent_forum_id);
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
            || $user->isModerator($this);
    }

    /**
     * Статус возможности пометки всех тем прочтенными
     *
     * @return bool
     */
    protected function getcanMarkRead()
    {
        return ! $this->c->user->isGuest; // ????
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
                $sub[$id] = $this->c->forums->get($id);
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
                $all[$id] = $this->c->forums->get($id);
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
        if (0 === $this->id) {
            return $this->c->Router->link('Index');
        } else {
            return $this->c->Router->link('Forum', ['id' => $this->id, 'name' => $this->forum_name]);
        }
    }

    /**
     * Ссылка на поиск новых сообщений
     *
     * @return string
     */
    protected function getlinkNew()
    {
        if (0 === $this->id) {
            return $this->c->Router->link('SearchAction', ['action' => 'new']);
        } else {
            return $this->c->Router->link('SearchAction', ['action' => 'new', 'forum' => $this->id]);
        }
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
     * Ссылка на пометку всех тем прочтенными
     *
     * @return string
     */
    protected function getlinkMarkRead()
    {
        return $this->c->Router->link('MarkRead', [
                'id'    => $this->id,
                'token' => $this->c->Csrf->create('MarkRead', ['id' => $this->id]),
            ]);
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

        if ($this->c->user->g_view_users == '1') {
            $arr = $this->a['moderators'];
            foreach($arr as $id => &$cur) {
                $cur = [
                    $this->c->Router->link('User', [
                        'id'   => $id,
                        'name' => $cur,
                    ]),
                    $cur,
                ];
            }
            unset($cur);
            return $arr;
        } else {
            return $this->a['moderators'];
        }
    }

    /**
     * Удаляет указанных пользователей из списка модераторов
     *
     * @param array ...$users
     *
     * @throws InvalidArgumentException
     */
    public function modDelete(...$users)
    {
        if (empty($this->a['moderators'])) {
            return;
        }

        $moderators = $this->a['moderators'];

        foreach ($users as $user) {
            if (! $user instanceof User) {
                throw new InvalidArgumentException('Expected User');
            }
            unset($moderators[$user->id]);
        }

        $this->moderators = $moderators;
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
            $this->a['tree'] = $this->c->forums->create([
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

        return (int) ceil(($this->num_topics ?: 1) / $this->c->user->disp_topics);
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
    public function pageData()
    {
        if (! $this->hasPage()) {
            throw new InvalidArgumentException('Bad number of displayed page');
        }

        if (empty($this->num_topics)) {
            return [];
        }

        switch ($this->sort_by) {
            case 1:
                $sortBy = 't.posted DESC';
                break;
            case 2:
                $sortBy = 't.subject ASC';
                break;
            default:
                $sortBy = 't.last_post DESC';
                break;
        }

        $vars = [
            ':fid'    => $this->id,
            ':offset' => ($this->page - 1) * $this->c->user->disp_topics,
            ':rows'   => $this->c->user->disp_topics,
        ];
        $sql = "SELECT t.id
                FROM ::topics AS t
                WHERE t.forum_id=?i:fid
                ORDER BY t.sticky DESC, {$sortBy}, t.id DESC
                LIMIT ?i:offset, ?i:rows";

        $this->idsList = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_COLUMN);

        return empty($this->idsList) ? [] : $this->c->topics->view($this);
    }

    /**
     * Возвращает значения свойств в массиве
     *
     * @return array
     */
    public function getAttrs()
    {
        $data = parent::getAttrs();

        $data['moderators'] = empty($data['moderators']) ? null : \json_encode($data['moderators']);

        return $data;
    }

}
