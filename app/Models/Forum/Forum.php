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
use ForkBB\Models\User\User;
use PDO;
use RuntimeException;
use InvalidArgumentException;

class Forum extends DataModel
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Forum';

    /**
     * Получение родительского раздела
     */
    protected function getparent(): ?Forum
    {
        if (null === $this->parent_forum_id) {
            if (0 !== $this->id) {
                throw new RuntimeException('Parent is not defined');
            }

            return null;
        } else {
            return $this->manager->get($this->parent_forum_id);
        }
    }

    /**
     * Возвращает название раздела
     */
    protected function getname(): ?string
    {
        return $this->forum_name;
    }

    /**
     * Возвращает название для формирования URL
     */
    protected function getfriendly(): ?string
    {
        return isset($this->friendly_name[0]) ? $this->friendly_name : $this->forum_name;
    }

    /**
     * Статус возможности создания новой темы
     */
    protected function getcanCreateTopic(): bool
    {
        $user = $this->c->user;

        return 1 === $this->post_topics
            || (
                null === $this->post_topics
                && 1 === $user->g_post_topics
            )
            || $user->isAdmin
            || $user->isModerator($this);
    }

    /**
     * Статус возможности пометки всех тем прочтенными
     */
    protected function getcanMarkRead(): bool
    {
        return ! $this->c->user->isGuest
            && ! $this->c->user->isUnverified;
    }

    /**
     * Статус возможности использования подписок
     */
    protected function getcanSubscription(): bool
    {
        return 1 === $this->c->config->b_forum_subscriptions
            && $this->id > 0
            && ! $this->c->user->isGuest
            && ! $this->c->user->isUnverified;
    }

    /**
     * Получение массива подразделов
     */
    protected function getsubforums(): array
    {
        $sub  = [];
        $attr = $this->getModelAttr('subforums');

        if (\is_array($attr)) {
            foreach ($attr as $id) {
                $sub[$id] = $this->manager->get($id);
            }
        }

        return $sub;
    }

    /**
     * Получение массива всех дочерних разделов
     */
    protected function getdescendants(): array
    {
        $all  = [];
        $attr = $this->getModelAttr('descendants');

        if (\is_array($attr)) {
            foreach ($attr as $id) {
                $all[$id] = $this->manager->get($id);
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
                    'name' => $this->friendly,
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
                    'forum'  => $this->id,
                ]
            );
        }
    }

    /**
     * Ссылка на последнее сообщение в разделе
     */
    protected function getlinkLast(): string
    {
        if ($this->last_post_id < 1) {
            return '';
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
            'MarkRead',
            [
                'id' => $this->id,
            ]
        );
    }

    /**
     * Ссылка на подписку
     */
    protected function getlinkSubscribe(): string
    {
        if ($this->id < 1) {
            return '';
        } else {
            return $this->c->Router->link(
                'ForumSubscription',
                [
                    'fid'  => $this->id,
                    'type' => 'subscribe',
                ]
            );
        }
    }

    /**
     * Ссылка на отписку
     */
    protected function getlinkUnsubscribe(): string
    {
        if ($this->id < 1) {
            return '';
        } else {
            return $this->c->Router->link(
                'ForumSubscription',
                [
                    'fid'  => $this->id,
                    'type' => 'unsubscribe',
                ]
            );
        }
    }

    /**
     * Получение массива модераторов
     */
    protected function getmoderators(): array
    {
        $attr = $this->getModelAttr('moderators');

        if (
            empty($attr)
            || ! \is_array($attr)
        ) {
            return [];
        }

        $viewUsers = $this->c->userRules->viewUsers;

        foreach ($attr as $id => &$cur) {
            $cur = [
                'name' => $cur,
                'link' => $viewUsers ?
                    $this->c->Router->link(
                        'User',
                        [
                            'id'   => $id,
                            'name' => $this->c->Func->friendly($cur),
                        ]
                    )
                    : null,
            ];
        }

        unset($cur);

        return $attr;
    }

    /**
     * Добавляет указанных пользователей в список модераторов
     */
    public function modAdd(User ...$users): void
    {
        $attr = $this->getModelAttr('moderators');

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
        $attr = $this->getModelAttr('moderators');

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
        $attr = $this->getModelAttr('tree');

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

            $attr = $this->manager->create([
                'num_topics'   => $numT,
                'num_posts'    => $numP,
                'last_post'    => $time,
                'last_post_id' => $postId,
                'last_poster'  => $poster,
                'last_topic'   => $topic,
                'newMessages'  => $fnew,
            ]);

            $this->setModelAttr('tree', $attr);
        }

        return $attr;
    }

    /**
     * Количество страниц в разделе
     */
    protected function getnumPages(): int
    {
        if (! \is_int($this->num_topics)) {
            throw new RuntimeException('The model does not have the required data');
        }

        return (int) \ceil(($this->num_topics ?: 1) / $this->c->user->disp_topics);
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
                'name' => $this->friendly,
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

        $this->createIdsList(
            $this->c->user->disp_topics,
            ($this->page - 1) * $this->c->user->disp_topics
        );

        return empty($this->idsList) ? [] : $this->c->topics->view($this);
    }

    /**
     * Вычисляет страницу раздела на которой находится данная тема
     */
    public function calcPage(int $tid): void
    {
        $this->createIdsList();

        $arr = \array_flip($this->idsList);

        if (isset($arr[$tid])) {
            $this->page = (int) \ceil(($arr[$tid] + 1) / $this->c->user->disp_topics);
        } else {
            $this->page = null;
        }
    }

    /**
     * Создает список id тем раздела
     */
    protected function createIdsList(int $rows = null, int $offset = null): void
    {
        $sortBy = match ($this->sort_by) {
            1       => 't.posted DESC',
            2       => 't.subject',
            4       => 't.last_post',
            5       => 't.posted',
            6       => 't.subject DESC',
            default => 't.last_post DESC',
        };

        $vars = [
            ':fid'    => $this->id,
            ':offset' => $offset,
            ':rows'   => $rows,
        ];
        $query = "SELECT t.id
            FROM ::topics AS t
            WHERE t.forum_id=?i:fid
            ORDER BY t.sticky DESC, {$sortBy}, t.id DESC";

        if (isset($rows, $offset)) {
            $query .= ' LIMIT ?i:rows OFFSET ?i:offset';
        }

        $this->idsList = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Возвращает значения свойств в массиве
     */
    public function getModelAttrs(): array
    {
        $data = parent::getModelAttrs();

        $data['moderators'] = empty($data['moderators']) || ! \is_array($data['moderators'])
            ? ''
            : \json_encode($data['moderators'], FORK_JSON_ENCODE);

        return $data;
    }

    protected function getcustom_fields(): array
    {
        $attr = $this->getModelAttr('custom_fields');

        if (
            ! \is_string($attr)
            || ! \is_array($attr = \json_decode($attr, true, 512, \JSON_THROW_ON_ERROR))
        ) {
            return [];
        } else {
            return $attr;
        }
    }

    protected function setcustom_fields(string|array|null $value): void
    {
        if (\is_array($value)) {
            $value = \json_encode($value, FORK_JSON_ENCODE);
        } elseif (
            ! \is_string($value)
            || ! \is_array(\json_decode($value, true))
        ) {
            $value = null;
        }

        $this->setModelAttr('custom_fields', $value);
    }
}
