<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\PM;

use ForkBB\Core\Container;
use ForkBB\Models\DataModel;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\User\Model as User;
use PDO;
use RuntimeException;

class PTopic extends DataModel
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->zDepend = [
            'id'            => ['link', 'hasNew', 'linkNew', 'firstNew', 'pagination', 'dataReply', 'linkReply'],
            'first_post_id' => ['firstNew'],
            'last_number'   => ['last_poster'],
            'last_post'     => ['firstNew'],
            'last_post_id'  => ['linkLast', 'firstNew'],
            'num_replies'   => ['numPages', 'pagination'],
            'poster'        => ['last_poster', 'byOrFor', 'zpUser', 'ztUser'],
            'poster_id'     => ['closed', 'firstNew', 'zp', 'zt', 'zpUser', 'ztUser', 'actionsAllowed', 'canReply'],
            'poster_status' => ['closed', 'actionsAllowed', 'canReply', 'isFullDeleted'],
            'poster_visit'  => ['firstNew'],
            'subject'       => ['name'],
            'target'        => ['last_poster', 'byOrFor', 'zpUser', 'ztUser'],
            'target_id'     => ['closed', 'firstNew', 'zp', 'zt', 'zpUser', 'ztUser', 'actionsAllowed', 'canReply'],
            'target_status' => ['closed', 'actionsAllowed', 'canReply', 'isFullDeleted'],
            'target_visit'  => ['firstNew'],
        ];
    }

    /**
     * Префикс текущего пользователя
     */
    protected function getzp(): string
    {
        if ($this->poster_id === $this->c->user->id) {
            return 'poster';
        } elseif ($this->target_id === $this->c->user->id) {
            return 'target';
        } else {
            throw new RuntimeException('Bad current user');
        }
    }

    /**
     * Префикс второго пользователя
     */
    protected function getzt(): string
    {
        if ($this->poster_id === $this->c->user->id) {
            return 'target';
        } elseif ($this->target_id === $this->c->user->id) {
            return 'poster';
        } else {
            throw new RuntimeException('Bad current user');
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
     * Ссылка на тему
     */
    protected function getlink(): string
    {
        return $this->c->Router->link(
            'PMAction',
            [
                'second' => $this->c->pms->second,
                'action' => Cnst::ACTION_TOPIC,
                'more1'  => $this->id,
            ]
        );
    }

    /**
     * Ссылка для перехода на последнее сообщение темы
     */
    protected function getlinkLast(): string
    {
        if ($this->last_post_id < 1) {
            return '';
        } else {
            return $this->c->Router->link(
                'PMAction',
                [
                    'second'  => $this->c->pms->second,
                    'action'  => Cnst::ACTION_POST,
                    'more1'   => $this->last_post_id,
                    'numPost' => $this->last_post_id,
                ]
            );
        }
    }

    /**
     * Ссылка для перехода на первое новое сообщение в теме
     */
    protected function getlinkNew(): string
    {
        return $this->c->Router->link(
            'PMAction',
            [
                'second' => $this->c->pms->second,
                'action' => Cnst::ACTION_TOPIC,
                'more1'  => $this->id,
                'more2'  => 'new',
            ]
        );
    }

    /**
     * Ссылка на уделение темы
     */
    protected function getlinkDelete(): string
    {
        return $this->c->Router->link(
            'PMAction',
            [
                'second' => $this->c->pms->second,
                'action' => Cnst::ACTION_DELETE,
                'more1'  => $this->id,
                'more2'  => Cnst::ACTION_TOPIC,
            ]
        );
    }

    /**
     * Номер первого нового сообщения в теме
     */
    protected function getfirstNew(): int
    {
        if (! $this->hasNew) {
            return 0;
        }

        $visit = $this->{"{$this->zp}_visit"};

        if ($visit < 1) {
            return $this->first_post_id;
        } elseif ($visit >= $this->last_post) {
            return $this->last_post_id;
        }

        $vars = [
            ':tid'   => $this->id,
            ':visit' => $visit,
        ];
        $query = 'SELECT MIN(pp.id)
            FROM ::pm_posts AS pp
            WHERE pp.topic_id=?i:tid AND pp.posted>?i:visit';

        $pid = $this->c->DB->query($query, $vars)->fetchColumn();

        return $pid ?: 0;
    }

    protected function setsender(User $user): void
    {
        $this->poster    = $user->username;
        $this->poster_id = $user->id;
    }

    protected function setrecipient(User $user): void
    {
        $this->target    = $user->username;
        $this->target_id = $user->id;
    }

    protected function user(string $prx): User
    {
        $user = $this->c->users->load($this->{"{$prx}_id"});

        if (! $user instanceof User) {
            throw new RuntimeException('User model could not be loaded ');
        } elseif ($user->isGuest) {
            $user = clone $user;

            $user->__username = $this->{$prx};
        }

        return $user;
    }

    protected function getzpUser(): User
    {
        return $this->user($this->zp);
    }

    protected function getztUser(): User
    {
        return $this->user($this->zt);
    }

    protected function setstatus(int $status): void
    {
        if ('poster' === $this->zp) {
            $tStatus = $status;

            switch ($status) {
                case Cnst::PT_ARCHIVE:
                    $tStatus = Cnst::PT_NOTSENT;
                case Cnst::PT_DELETED:
                case Cnst::PT_NORMAL:
                    $this->poster_status = $status;

                    if (null === $this->target_status) {
                        $this->target_status = $tStatus;
                    }

                    return;
            }
        } else {
            switch ($status) {
                case Cnst::PT_ARCHIVE:
                case Cnst::PT_DELETED:
                    $this->target_status = $status;

                    return;
            }
        }

        throw new RuntimeException("Bad status: {$status}");
    }

    /**
     * Возвращает имя автора последнего поста или пустую строку
     */
    protected function getlast_poster(): string
    {
        if (0 === $this->last_number) {
            return $this->poster;
        } elseif (1 === $this->last_number) {
            return $this->target;
        } else {
            return '';
        }
    }

    /**
     * Статус наличия новых сообщений в теме
     */
    protected function gethasNew(): bool
    {
        return isset($this->c->pms->idsNew[$this->id]);
    }

    protected function getbyOrFor(): array
    {
        if ('poster' === $this->zp) {
            return ['for %s', $this->target];
        } else {
            return ['by %s', $this->poster];
        }
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
     * Статус наличия установленной страницы в теме
     */
    public function hasPage(): bool
    {
        return $this->page > 0 && $this->page <= $this->numPages;
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
        } else {
            return $this->c->Func->paginate(
                $this->numPages,
                $page,
                'PMAction',
                [
                    'second' => $this->c->pms->second,
                    'action' => Cnst::ACTION_TOPIC,
                    'more1'  => $this->id,
                    'page'   => 'more2', // нестандарная переменная для page
                ]
            );
        }
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
        $query = 'SELECT COUNT(pp.id) AS pnum, MAX(pp.id) as pmax
            FROM ::pm_posts AS pp
            WHERE pp.topic_id=?i:tid AND pp.id<=?i:pid';

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
     * Возвращает массив сообщений с установленной страницы
     */
    public function pageData(): array
    {
        if (! $this->hasPage()) {
            throw new InvalidArgumentException('Bad number of displayed page');
        }

        $count = ($this->page - 1) * $this->c->user->disp_posts;
        $vars  = [
            ':tid'    => $this->id,
            ':offset' => $count,
            ':rows'   => $this->c->user->disp_posts,
        ];
        $query = 'SELECT pp.id
            FROM ::pm_posts AS pp
            WHERE pp.topic_id=?i:tid
            ORDER BY pp.id
            LIMIT ?i:offset, ?i:rows';

        $list  = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
        $posts = $this->c->pms->loadByIds(Cnst::PPOST, $list);

        foreach ($posts as $post) {
            ++$count;

            if ($post instanceof PPost) {
                $post->__postNumber = $count;
            }
        }

        return $posts;
    }

    /**
     * Обновляет метку визита
     */
    public function updateVisit(): void
    {
        $visit = $this->{"{$this->zp}_visit"};

        if ($visit >= $this->last_post) {
            return;
        }

        $this->{"{$this->zp}_visit"} = $this->last_post;

        $this->c->pms->update(Cnst::PTOPIC, $this);
        $this->c->pms->recalculate($this->zpUser);
    }

    /**
     * Возвращает массив сообщений обзора темы
     */
    public function review(): array
    {
        if ($this->c->config->i_topic_review < 1) {
            return [];
        }

        $count = $this->num_replies + 1;
        $vars  = [
            ':tid'  => $this->id,
            ':rows' => $this->c->config->i_topic_review,
        ];
        $query = 'SELECT pp.id
            FROM ::pm_posts AS pp
            WHERE pp.topic_id=?i:tid
            ORDER BY pp.id DESC
            LIMIT 0, ?i:rows';

        $list  = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
        $posts = $this->c->pms->loadByIds(Cnst::PPOST, $list);

        foreach ($posts as $post) {
            if ($post instanceof PPost) {
                $post->__postNumber = $count;
            }

            --$count;
        }

        return $posts;
    }

    /**
     * Аргументы для ссылки для ответа в теме
     */
    protected function getdataReply(): array
    {
        return [
            'second' => $this->c->pms->second,
            'action' => Cnst::ACTION_SEND,
            'more1'  => $this->id,
        ];
    }

    /**
     * Ссылка для ответа в теме
     */
    protected function getlinkReply(): string
    {
        return $this->c->Router->link('PMAction', $this->dataReply);
    }

    /**
     * Статус закрытия темы
     */
    protected function getclosed(): bool
    {
        $p = $this->{"{$this->zp}_status"};
        $t = $this->{"{$this->zt}_status"};

        return Cnst::PT_DELETED === $t
            || Cnst::PT_ARCHIVE === $t
            || (
                Cnst::PT_ARCHIVE === $p
                && Cnst::PT_NOTSENT !== $t
            );
    }

    /**
     * Статус возможности действий
     */
    protected function getactionsAllowed(): bool
    {
        return ! $this->closed
            && $this->zpUser->usePM
            && $this->ztUser->usePM;
    }

    /**
     * Статус возможности ответа в теме
     */
    protected function getcanReply(): bool
    {
        return $this->actionsAllowed
            && (
                (
                    1 === $this->zpUser->u_pm
                    && 1 === $this->ztUser->u_pm
                )
                || (
                    Cnst::PT_ARCHIVE === $this->{"{$this->zp}_status"}
                    && Cnst::PT_NOTSENT === $this->{"{$this->zt}_status"}
                )
                || $this->zpUser->isAdmin
                || $this->ztUser->isAdmin
            );
    }

    /**
     * Статус удаления диалога у обоих собеседников
     */
    protected function getisFullDeleted(): bool
    {
        return (
                Cnst::PT_DELETED === $this->poster_status
                || Cnst::PT_NOTSENT === $this->poster_status
            )
            && (
                Cnst::PT_DELETED === $this->target_status
                || Cnst::PT_NOTSENT === $this->target_status
            );
    }
}
