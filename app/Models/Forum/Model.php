<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Forum;

use ForkBB\Models\DataModel;
use ForkBB\Models\User\Model as User;
use ForkBB\Models\Forum\Model as Forum;
use PDO;
use RuntimeException;
use InvalidArgumentException;

class Model extends DataModel
{
    /**
     * Получение родительского раздела
     */
    protected function getparent(): ?Forum
    {
        if (
            null === $this->parent_forum_id
            && 0 !== $this->id
        ) {
            throw new RuntimeException('Parent is not defined');
        }

        return $this->c->forums->get($this->parent_forum_id);
    }

    /**
     * Возвращает название раздела
     */
    protected function getname(): ?string
    {
        return $this->forum_name;
    }

    /**
     * Статус возможности создания новой темы
     */
    protected function getcanCreateTopic(): bool
    {
        $user = $this->c->user;

        return 1 == $this->post_topics
            || (
                null === $this->post_topics
                && 1 == $user->g_post_topics
            )
            || $user->isAdmin
            || $user->isModerator($this);
    }

    /**
     * Статус возможности пометки всех тем прочтенными
     */
    protected function getcanMarkRead(): bool
    {
        return ! $this->c->user->isGuest; // ????
    }

    /**
     * Статус возможности использования подписок
     */
    protected function getcanSubscription(): bool
    {
        return '1' == $this->c->config->o_forum_subscriptions
            && $this->id > 0
            && ! $this->c->user->isGuest
            && ! $this->c->user->isUnverified;
    }

    /**
     * Получение массива подразделов
     */
    protected function getsubforums(): array
    {
        $sub = [];
        $attr = $this->getAttr('subforums');

        if (\is_array($attr)) {
            foreach ($attr as $id) {
                $sub[$id] = $this->c->forums->get($id);
            }
        }

        return $sub;
    }

    /**
     * Получение массива всех дочерних разделов
     */
    protected function getdescendants(): array
    {
        $all = [];
        $attr = $this->getAttr('descendants');

        if (\is_array($attr)) {
            foreach ($attr as $id) {
                $all[$id] = $this->c->forums->get($id);
            }
        }

        return $all;
    }

    /**
     * Ссылка на раздел
     */
    protected function getlink(): string
    {
        if (0 === $this->id) {
            return $this->c->Router->link('Index');
        } else {
            return $this->c->Router->link(
                'Forum',
                [
                    'id'   => $this->id,
                    'name' => $this->forum_name,
                ]
            );
        }
    }

    /**
     * Ссылка на поиск новых сообщений
     */
    protected function getlinkNew(): string
    {
        if (0 === $this->id) {
            return $this->c->Router->link(
                'SearchAction',
                [
                    'action' => 'new',
                ]
            );
        } else {
            return $this->c->Router->link(
                'SearchAction',
                [
                    'action' => 'new',
                    'forum' => $this->id,
                ]
            );
        }
    }

    /**
     * Ссылка на последнее сообщение в разделе
     */
    protected function getlinkLast(): ?string
    {
        if ($this->last_post_id < 1) {
            return null;
        } else {
            return $this->c->Router->link(
                'ViewPost',
                [
                    'id' => $this->last_post_id,
                ]
            );
        }
    }

    /**
     * Ссылка на создание новой темы
     */
    protected function getlinkCreateTopic(): string
    {
        return $this->c->Router->link(
            'NewTopic',
            [
                'id' => $this->id,
            ]
        );
    }

    /**
     * Ссылка на пометку всех тем прочтенными
     */
    protected function getlinkMarkRead(): string
    {
        return $this->c->Router->link(
            'MarkRead', [
                'id'    => $this->id,
                'token' => null,
            ]
        );
    }

    /**
     * Ссылка на подписку
     */
    protected function getlinkSubscribe(): ?string
    {
        if ($this->id < 1) {
            return null;
        } else {
            return $this->c->Router->link(
                'ForumSubscription',
                [
                    'fid'   => $this->id,
                    'type'  => 'subscribe',
                    'token' => null,
                ]
            );
        }
    }

    /**
     * Ссылка на отписку
     */
    protected function getlinkUnsubscribe(): ?string
    {
        if ($this->id < 1) {
            return null;
        } else {
            return $this->c->Router->link(
                'ForumSubscription',
                [
                    'fid'   => $this->id,
                    'type'  => 'unsubscribe',
                    'token' => null,
                ]
            );
        }
    }

    /**
     * Получение массива модераторов
     */
    protected function getmoderators(): array
    {
        $attr = $this->getAttr('moderators');
        if (
            empty($attr)
            || ! \is_array($attr)
        ) {
            return [];
        }

        if ('1' == $this->c->user->g_view_users) {
            foreach($attr as $id => &$cur) {
                $cur = [
                    $this->c->Router->link(
                        'User',
                        [
                            'id'   => $id,
                            'name' => $cur,
                        ]
                    ),
                    $cur,
                ];
            }
            unset($cur);
        }

        return $attr;
    }

    /**
     * Добавляет указанных пользователей в список модераторов
     */
    public function modAdd(User ...$users): void
    {
        $attr = $this->getAttr('moderators');
        if (
            empty($attr)
            || ! \is_array($attr)
        ) {
            $attr = [];
        }

        foreach ($users as $user) {
            if (! $user instanceof User) {
                throw new InvalidArgumentException('Expected User');
            }
            $attr[$user->id] = $user->username;
        }

        $this->moderators = $attr;
    }

    /**
     * Удаляет указанных пользователей из списка модераторов
     */
    public function modDelete(User ...$users): void
    {
        $attr = $this->getAttr('moderators');
        if (
            empty($attr)
            || ! \is_array($attr)
        ) {
            return;
        }

        foreach ($users as $user) {
            if (! $user instanceof User) {
                throw new InvalidArgumentException('Expected User');
            }
            unset($attr[$user->id]);
        }

        $this->moderators = $attr;
    }

    /**
     * Возвращает общую статистику по дереву разделов с корнем в текущем разделе
     */
    protected function gettree(): Forum
    {
        $attr = $this->getAttr('tree');

        if (empty($attr)) { //????
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
            $attr = $this->c->forums->create([
                'num_topics'     => $numT,
                'num_posts'      => $numP,
                'last_post'      => $time,
                'last_post_id'   => $postId,
                'last_poster'    => $poster,
                'last_topic'     => $topic,
                'newMessages'    => $fnew,
            ]);

            $this->setAttr('tree', $attr);
        }

        return $attr;
    }

    /**
     * Количество страниц в разделе
     */
    protected function getnumPages(): int
    {
        if (null === $this->num_topics) {
            throw new RuntimeException('The model does not have the required data');
        }

        return (int) ceil(($this->num_topics ?: 1) / $this->c->user->disp_topics);
    }

    /**
     * Массив страниц раздела
     */
    protected function getpagination(): array
    {
        return $this->c->Func->paginate(
            $this->numPages,
            $this->page,
            'Forum',
            [
                'id'   => $this->id,
                'name' => $this->forum_name,
            ]
        );
    }

    /**
     * Статус наличия установленной страницы в разделе
     */
    public function hasPage(): bool
    {
        return $this->page > 0 && $this->page <= $this->numPages;
    }

    /**
     * Возвращает массив тем с установленной страницы
     */
    public function pageData(): array
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
        $query = "SELECT t.id
            FROM ::topics AS t
            WHERE t.forum_id=?i:fid
            ORDER BY t.sticky DESC, {$sortBy}, t.id DESC
            LIMIT ?i:offset, ?i:rows";

        $this->idsList = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

        return empty($this->idsList) ? [] : $this->c->topics->view($this);
    }

    /**
     * Возвращает значения свойств в массиве
     */
    public function getAttrs(): array
    {
        $data = parent::getAttrs();

        $data['moderators'] = empty($data['moderators']) || ! \is_array($data['moderators'])
            ? ''
            : \json_encode($data['moderators']);

        return $data;
    }
}
