<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\PM;

use ForkBB\Core\Container;
use ForkBB\Models\DataModel;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PTopic;
use ForkBB\Models\User\User;
use InvalidArgumentException;
use RuntimeException;

class PPost extends DataModel
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'PPost';

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->zDepend = [
            'id'            => ['link', 'user', 'canDelete', 'linkDelete', 'linkEdit', 'linkQuote', 'previousId', 'linkPrevious', 'linkBlock'],
            'edited'        => ['editor'],
            'posted'        => ['canDelete', 'canEdit', 'canBlock'],
            'poster'        => ['editor'],
            'poster_id'     => ['canDelete', 'canEdit', 'canBlock', 'linkBlock'],
            'topic_id'      => ['parent', 'linkQuote', 'previousId', 'linkPrevious'],
        ];
    }

    protected function getparent(): ?PTopic
    {
        if ($this->topic_id < 1) {
            throw new RuntimeException('Parent is not defined');
        }

        return $this->c->pms->load(Cnst::PTOPIC, $this->topic_id);
    }

    public function setuser(User $user): void
    {
        if (
            $user->isGuest
            || $user->isUnverified
        ) {
            throw new InvalidArgumentException('Bad user');
        }

        $this->poster    = $user->username;
        $this->poster_id = $user->id;
    }

    protected function getuser(): User
    {
        if (
            $this->poster_id < 1
            || ! ($user = $this->c->users->load($this->poster_id)) instanceof User
        ) {
            $user = $this->c->users->guest(['username' => $this->poster]);
        }

        if (! $user instanceof User) {
            throw new RuntimeException("No user data in ppost number {$this->id}");
        }

        return $user;
    }

    protected function geteditor(): string
    {
        return $this->edited > 0 ? $this->poster : '';
    }

    /**
     * Ссылка на пост
     */
    protected function getlink(): string
    {
        return $this->c->Router->link(
            'PMAction',
            [
                'second'  => $this->c->pms->second,
                'action'  => Cnst::ACTION_POST,
                'more1'   => $this->id,
                'numPost' => $this->id,
            ]
        );
    }

    /**
     * Ссылка на предыдущий пост
     */
    protected function getlinkPrevious(): string
    {
        return $this->c->Router->link(
            'PMAction',
            [
                'second'  => $this->c->pms->second,
                'action'  => Cnst::ACTION_POST,
                'more1'   => $this->previousId,
                'numPost' => $this->previousId,
            ]
        );
    }

    /**
     * Статус возможности удаления
     */
    protected function getcanDelete(): bool
    {
        return $this->parent->actionsAllowed
            && $this->poster_id === $this->c->user->id
            && $this->id !== $this->parent->first_post_id
            && $this->posted > $this->parent->{"{$this->parent->zt}_visit"};
    }

    /**
     * Ссылка на страницу удаления
     */
    protected function getlinkDelete(): string
    {
        return $this->c->Router->link(
            'PMAction',
            [
                'second' => $this->c->pms->second,
                'action' => Cnst::ACTION_DELETE,
                'more1'  => $this->id,
                'more2'  => Cnst::ACTION_POST,
            ]
        );
    }

    /**
     * Статус возможности редактирования
     */
    protected function getcanEdit(): bool
    {
        return $this->parent->actionsAllowed
            && $this->poster_id === $this->c->user->id
            && $this->posted > $this->parent->{"{$this->parent->zt}_visit"};
    }

    /**
     * Ссылка на страницу редактирования
     */
    protected function getlinkEdit(): string
    {
        return $this->c->Router->link(
            'PMAction',
            [
                'second' => $this->c->pms->second,
                'action' => Cnst::ACTION_EDIT,
                'more1'  => $this->id,
            ]
        );
    }

    /**
     * Статус возможности ответа с цитированием
     */
    protected function getcanQuote(): bool
    {
        return $this->parent->canReply;
    }

    /**
     * Ссылка на страницу ответа с цитированием
     */
    protected function getlinkQuote(): string
    {
        return $this->c->Router->link(
            'PMAction',
            [
                'second' => $this->c->pms->second,
                'action' => Cnst::ACTION_SEND,
                'more1'  => $this->topic_id,
                'more2'  => $this->id,
            ]
        );
    }

    /**
     * Статус возможности (раз)блокировки
     */
    protected function getcanBlock(): bool
    {
        return $this->c->pms->block->canBlock($this->user);
    }

    /**
     * Ссылка на (раз)блокировку пользователя
     */
    protected function getlinkBlock(): string
    {
        return $this->c->Router->link(
            'PMAction',
            [
                'second' => $this->c->pms->second,
                'action' => Cnst::ACTION_BLOCK,
                'more1'  => $this->poster_id,
                'more2'  => $this->id,
            ]
        );
    }

    /**
     * HTML код сообщения
     */
    public function html(): string
    {
        return $this->c->censorship->censor($this->c->Parser->parseMessage($this->message, (bool) $this->hide_smilies));
    }

    /**
     * Вычисляет номер сообщения перед данным
     */
    public function getpreviousId(): ?int
    {
        $vars = [
            ':pid' => $this->id,
            ':tid' => $this->topic_id,
        ];
        $query = "SELECT MAX(pp.id)
            FROM ::pm_posts AS pp
            WHERE pp.id < ?i:pid AND pp.topic_id=?i:tid";

        $id = (int) $this->c->DB->query($query, $vars)->fetchColumn();

        return $id ?: null;
    }
}
