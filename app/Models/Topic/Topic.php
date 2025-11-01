<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Topic;

use ForkBB\Core\Container;
use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Poll\Poll;
use ForkBB\Models\Post\Post;
use PDO;
use RuntimeException;

class Topic extends DataModel
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Topic';

    /**
     * Получение родительского раздела
     */
    protected function getparent(): ?Forum
    {
        if ($this->forum_id < 1) {
            throw new RuntimeException('Parent is not defined');
        }

        $forum = $this->c->forums->get($this->forum_id);

        if (
            ! $forum instanceof Forum
            || $forum->redirect_url
        ) {
            return null;

        } else {
            return $forum;
        }
    }

    /**
     * Возвращает отцензурированное название темы
     */
    protected function getname(): ?string
    {
        return $this->censorSubject;
    }

    /**
     * Возвращает название для формирования URL
     */
    protected function getfriendly(): ?string
    {
        return $this->c->Func->friendly($this->name);
    }

    /**
     * Статус возможности ответа в теме
     */
    protected function getcanReply(): bool
    {
        if ($this->moved_to) {
            return false;

        } elseif ($this->c->user->isAdmin) {
            return true;

        } elseif (
            $this->closed
            || $this->c->user->isBot
        ) {
            return false;

        } elseif (
            1 === $this->parent->post_replies
            || (
                null === $this->parent->post_replies
                && 1 === $this->c->user->g_post_replies
            )
            || $this->c->user->isModerator($this)
        ) {
            return true;

        } else {
            return false;
        }
    }

    /**
     * Статус возможности использования подписок
     */
    protected function getcanSubscription(): bool
    {
        return 1 === $this->c->config->b_topic_subscriptions
            && $this->id > 0
            && ! $this->c->user->isGuest
            && ! $this->c->user->isUnverified;
    }

    /**
     * Ссылка на тему
     */
    protected function getlink(): string
    {
        return $this->c->Router->link(
            'Topic',
            [
                'id'   => $this->moved_to ?: $this->id,
                'name' => $this->friendly,
            ]
        );
    }

    /**
     * Ссылка для ответа в теме
     */
    protected function getlinkReply(): string
    {
        return $this->c->Router->link(
            'NewReply',
            [
                'id' => $this->id,
            ]
        );
    }

    /**
     * Ссылка для перехода на последнее сообщение темы
     */
    protected function getlinkLast(): string
    {
        if (
            $this->moved_to
            || $this->last_post_id < 1
        ) {
            return '';

        } else {
            return $this->c->Router->link(
                'Topic',
                [
                    'id'   => $this->moved_to ?: $this->id,
                    'name' => $this->friendly,
                    'page' => $this->numPages,
                    '#'    => 'p' . $this->last_post_id,
                ]
            );
            /*
            return $this->c->Router->link(
                'ViewPost',
                [
                    'id' => $this->last_post_id,
                ]
            );
            */
        }
    }

    /**
     * Ссылка для перехода на первое новое сообщение в теме
     */
    protected function getlinkNew(): string
    {
        return $this->c->Router->link(
            'TopicViewNew',
            [
                'id' => $this->id,
            ]
        );
    }

    /**
     * Ссылка для перехода на первое не прочитанное сообщение в теме
     */
    protected function getlinkUnread(): string
    {
        return $this->c->Router->link(
            'TopicViewUnread',
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
        return $this->c->Router->link(
            'TopicSubscription',
            [
                'tid'  => $this->id,
                'type' => 'subscribe',
            ]
        );
    }

    /**
     * Ссылка на отписку
     */
    protected function getlinkUnsubscribe(): string
    {
        return $this->c->Router->link(
            'TopicSubscription',
            [
                'tid'  => $this->id,
                'type' => 'unsubscribe',
            ]
        );
    }

    /**
     * Дополнительная ссылка для хлебных крошек
     */
    protected function getlinkCrumbExt(): ?string
    {
        if ($this->c->user->isGuest) {
            return null;

        } else {
            return $this->c->Router->link(
                'ForumScrollToTopic',
                [
                    'tid'  => $this->id,
                ]
            );
        }
    }

    /**
     * Статус наличия новых сообщений в теме
     */
    protected function gethasNew(): int|false
    {
        if (
            $this->c->user->isGuest
            || $this->moved_to
        ) {
            return false;
        }

        $time = \max(
            (int) $this->c->user->u_mark_all_read,
            (int) $this->parent->mf_mark_all_read,
            (int) $this->c->user->last_visit,
            (int) $this->mt_last_visit
        );

        return $this->last_post > $time ? $time : false;
    }

    /**
     * Статус наличия непрочитанных сообщений в теме
     */
    protected function gethasUnread(): int|false
    {
        if (
            $this->c->user->isGuest
            || $this->moved_to
        ) {
            return false;
        }

        $time = \max(
            (int) $this->c->user->u_mark_all_read,
            (int) $this->parent->mf_mark_all_read,
            (int) $this->mt_last_read
        );

        return $this->last_post > $time ? $time : false;
    }

    /**
     * Номер первого нового сообщения в теме
     */
    protected function getfirstNew(): int
    {
        if (false === $this->hasNew) {
            return 0;

        } elseif ($this->posted > $this->hasNew) {
            return $this->first_post_id;
        }

        $vars = [
            ':tid'   => $this->id,
            ':visit' => $this->hasNew,
        ];
        $query = 'SELECT MIN(p.id)
            FROM ::posts AS p
            WHERE p.topic_id=?i:tid AND p.posted>?i:visit';

        return (int) $this->c->DB->query($query, $vars)->fetchColumn();
    }

    /**
     * Номер первого не прочитанного сообщения в теме
     */
    protected function getfirstUnread(): int
    {
        if (false === $this->hasUnread) {
            return 0;

        } elseif ($this->posted > $this->hasUnread) {
            return $this->first_post_id;
        }

        $vars = [
            ':tid'   => $this->id,
            ':visit' => $this->hasUnread,
        ];
        $query = 'SELECT MIN(p.id)
            FROM ::posts AS p
            WHERE p.topic_id=?i:tid AND p.posted>?i:visit';

        return (int) $this->c->DB->query($query, $vars)->fetchColumn();
    }

    /**
     * Количество страниц в теме
     */
    protected function getnumPages(): int
    {
        if (null === $this->num_replies) {
            throw new RuntimeException('The model does not have the required data');
        }

        return (int) \ceil(($this->num_replies + 1) / $this->c->user->disp_posts);
    }

    /**
     * Массив страниц темы
     */
    protected function getpagination(): array
    {
        $page = (int) $this->page;

        if (
            $page < 1
            && 1 === $this->numPages
        ) {
            // 1 страницу в списке тем раздела не отображаем
            return [];

        } else { //????
            return $this->c->Func->paginate(
                $this->numPages,
                $page,
                'Topic',
                [
                    'id'   => $this->id,
                    'name' => $this->friendly,
                ]
            );
        }
    }

    /**
     * Статус наличия установленной страницы в теме
     */
    public function hasPage(): bool
    {
        return $this->page > 0 && $this->page <= $this->numPages;
    }

    /**
     * Возвращает массив сообщений с установленной страницы
     */
    public function pageData(): array
    {
        if (! $this->hasPage()) {
            throw new InvalidArgumentException('Bad number of displayed page');
        }

        $vars = [
            ':tid'    => $this->id,
            ':offset' => ($this->page - 1) * $this->c->user->disp_posts,
            ':rows'   => $this->c->user->disp_posts,
        ];
        $query = 'SELECT p.id
            FROM ::posts AS p
            WHERE p.topic_id=?i:tid
            ORDER BY p.id
            LIMIT ?i:rows OFFSET ?i:offset';

        $list = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

        if (
            ! empty($list)
            && (
                $this->stick_fp
                || (
                    $this->poll_type > 0
                    && 1 === $this->c->config->b_poll_enabled
                )
            )
            && ! \in_array($this->first_post_id, $list)
        ) {
            \array_unshift($list, $this->first_post_id);
        }

        $this->idsList = $list;

        return empty($this->idsList) ? [] : $this->c->posts->view($this);
    }

    /**
     * Возвращает массив сообщений обзора темы
     */
    public function review(): array
    {
        if ($this->c->config->i_topic_review < 1) {
            return [];
        }

        $this->page = 1;

        $vars = [
            ':tid'  => $this->id,
            ':rows' => $this->c->config->i_topic_review,
        ];
        $query = 'SELECT p.id
            FROM ::posts AS p
            WHERE p.topic_id=?i:tid
            ORDER BY p.id DESC
            LIMIT ?i:rows';

        $this->idsList = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

        return empty($this->idsList) ? [] : $this->c->posts->view($this, true);
    }

    /**
     * Вычисляет страницу темы на которой находится данное сообщение
     */
    public function calcPage(int $pid): void
    {
        $vars = [
            ':tid' => $this->id,
            ':pid' => $pid,
        ];
        $query = 'SELECT COUNT(p.id) AS pnum, MAX(p.id) as pmax
            FROM ::posts AS p
            WHERE p.topic_id=?i:tid AND p.id<=?i:pid';

        $result = $this->c->DB->query($query, $vars)->fetch();

        if (
            empty($result['pmax'])
            || $result['pmax'] !== $pid
        ) {
            $this->page = null;

        } else {
            $this->page = (int) \ceil($result['pnum'] / $this->c->user->disp_posts);
        }
    }

    /**
     * Статус показа/подсчета просмотров темы
     */
    protected function getshowViews(): bool
    {
        return 1 === $this->c->config->b_topic_views;
    }

    /**
     * Увеличивает на 1 количество просмотров темы
     */
    public function incViews(): void
    {
        $vars = [
            ':tid' => $this->id,
        ];
        $query = 'UPDATE ::topics
            SET num_views=num_views+1
            WHERE id=?i:tid';

        $this->c->DB->exec($query, $vars);
    }

    /**
     * Обновление меток последнего визита и последнего прочитанного сообщения
     */
    public function updateVisits(): void
    {
        if ($this->c->user->isGuest) {
            return;
        }

        $vars = [
            ':uid'   => $this->c->user->id,
            ':tid'   => $this->id,
            ':read'  => (int) $this->mt_last_read,
            ':visit' => (int) $this->mt_last_visit,
        ];
        $flag = false;

        if (false !== $this->hasNew) {
            $flag           = true;
            $vars[':visit'] = $this->last_post;
        }

        if (
            false !== $this->hasUnread
            && $this->timeMax > $this->hasUnread
        ) {
            $flag           = true;
            $vars[':read']  = $this->timeMax;
            $vars[':visit'] = $this->last_post;
        }

        if ($flag) {
            if (
                empty($this->mt_last_read)
                && empty($this->mt_last_visit)
            ) {
                $query = 'INSERT INTO ::mark_of_topic (uid, tid, mt_last_visit, mt_last_read)
                    SELECT tmp.*
                    FROM (SELECT ?i:uid AS f1, ?i:tid AS f2, ?i:visit AS f3, ?i:read AS f4) AS tmp
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM ::mark_of_topic
                        WHERE uid=?i:uid AND tid=?i:tid
                    )';

            } else {
                $query = 'UPDATE ::mark_of_topic
                    SET mt_last_visit=?i:visit, mt_last_read=?i:read
                    WHERE uid=?i:uid AND tid=?i:tid';
            }

            $this->c->DB->exec($query, $vars);
        }
    }

    /**
     * Возвращает опрос при его наличии
     */
    protected function getpoll(): ?Poll
    {
        return $this->poll_type > 0 ? $this->c->polls->load($this->id) : null;
    }

    /**
     * Возвращает статус возможности выбрать решение
     */
    protected function getcanChSolution(): bool
    {
        return 1 === $this->parent->use_solution
            && (
                $this->c->user->isAdmin
                || (
                    $this->c->user->id === $this->poster_id
                    && ! $this->c->user->isGuest
                )
            );
    }

    /**
     * Спислк bbcode для оглавления и их уровень //???? перенести в настройки?
     */
    protected array $idsLevel = [
        'h1' => 1,
        'h2' => 2,
        'h'  => 3,
        'h3' => 3,
        'h4' => 4,
        'h5' => 5,
        'h6' => 6,
    ];

    /**
     * Вносит изменение (возможно!?) в оглавление темы на основе переданного сообщения
     * Сообщение $post должно быть обработано парсером перед вызовом метода, чтобы парсер содержал дерево тегов этого сообщения
     */
    public function addPostToToc(Post $post, bool $merge = false): Topic
    {
        if (
            $post->poster_id < 1
            || $this->poster_id !== $post->poster_id
            || (
                empty($this->toc)
                && $this->first_post_id !== $post->id
            )
        ) {
            return $this;
        }

        $ids = $this->c->Parser->getIds(...(\array_keys($this->idsLevel)));

        if (empty($ids)) {
            if ($merge) {
                return $this;

            } elseif ($this->first_post_id === $post->id) {
                $this->toc = null;

                return $this;
            }
        }

        $r = [];

        foreach ($ids as $id => $tag) {
            $text  = \preg_replace('%^\s+|\s+$%uD', '', $this->c->Parser->getText($id));
            $ident = $this->c->Parser->createIdentifier($text);
            $r[]   = [$this->idsLevel[$tag], $ident, $text];
        }

        $toc = $this->toc ? \json_decode($this->toc, true, 512, \JSON_THROW_ON_ERROR) : [];

        if (
            $merge
            && ! empty($toc[$post->id])
        ) {
            $toc[$post->id] = \array_merge($toc[$post->id], $r);

        } else {
            $toc[$post->id] = $r;
        }

        \ksort($toc, \SORT_NUMERIC);

        $this->toc = \json_encode($toc, FORK_JSON_ENCODE);

        return $this;
    }

    /**
     * Удаляет из оглавления темы сообщения по их номеру
     */
    public function deleteFromToc(int ...$ids): Topic
    {
        if (empty($this->toc)) {
            return $this;
        }

        $toc = \json_decode($this->toc, true, 512, \JSON_THROW_ON_ERROR);
        $new = \array_diff_key($toc, \array_flip($ids));

        if ($toc !== $new) {
            $this->toc = \json_encode($new, FORK_JSON_ENCODE);
        }

        return $this;
    }

    /**
     * Возвращает список для построения оглавления темы
     */
    protected function gettableOfContent(): array
    {
        $toc     = $this->toc ? \json_decode($this->toc, true, 512, \JSON_THROW_ON_ERROR) : [];
        $out     = [];
        $visible = \is_array($this->idsList) ? \array_flip($this->idsList) : [];

        foreach ($toc as $pid => $list) {
            if (isset($visible[$pid])) {
                $link = '';

            } else {
                $link = $this->c->Router->link('ViewPost', ['id' => $pid]);
                $link = \strstr($link, '#', true) ?: $link;
            }

            foreach ($list as $cur) {
                $out[] = [
                    'level' => $cur[0],
                    'url'   => "{$link}#{$cur[1]}",
                    'value' => $cur[2],
                ];
            }
        }

        return $out;
    }

    /**
     * Возвращает ссылку на пост с решение
     */
    protected function getlinkGoToSolution(): string
    {
        if ($this->solution > 0) {
            return $this->c->Router->link('ViewPost', ['id' => $this->solution]);

        } else {
            return '';
        }
    }

    protected function getcf_data(): array
    {
        $attr = $this->getModelAttr('cf_data');

        if (
            ! \is_string($attr)
            || ! \is_array($attr = \json_decode($attr, true, 512, \JSON_THROW_ON_ERROR))
        ) {
            return [];

        } else {
            return $attr;
        }
    }

    protected function setcf_data(string|array|null $value): void
    {
        if (\is_array($value)) {
            $value = \json_encode($value, FORK_JSON_ENCODE);

        } elseif (
            ! \is_string($value)
            || ! \is_array(\json_decode($value, true))
        ) {
            $value = null;
        }

        $this->setModelAttr('cf_data', $value);
    }

    protected function getcustomFieldsCurLevel(): int
    {
        if (
            $this->cf_level < 1
            || empty($this->cf_data)
        ) {
            return 0;

        } elseif (
            $this->c->user->isAdmin
            || (
                $this->c->user->id === $this->poster_id
                && ! $this->c->user->isGuest
            )
        ) {
            $level = 4;

        } elseif ($this->c->user->isModerator($this)) {
            $level = 3;

        } elseif (! $this->c->user->isGuest) {
            $level = 2;

        } else {
            $level = 1;
        }

        return $this->cf_level > $level ? 0 : $level;
    }
}
