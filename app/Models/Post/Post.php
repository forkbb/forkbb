<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use RuntimeException;

class Post extends DataModel
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Post';

    /**
     * Получение родительской темы
     */
    protected function getparent(): ?Topic
    {
        if ($this->topic_id < 1) {
            throw new RuntimeException('Parent is not defined');
        }

        $topic = $this->c->topics->load($this->topic_id);

        if (
            ! $topic instanceof Topic
            || $topic->moved_to
            || ! $topic->parent instanceof Forum
        ) {
            return null;
        } else {
            return $topic;
        }
    }

    /**
     * Ссылка на сообщение
     */
    protected function getlink(): string
    {
        return $this->c->Router->link(
            'ViewPost',
            [
                'id' => $this->id,
            ]
        );
    }

    /**
     * Автор сообщения
     */
    protected function getuser(): User
    {
        if (
            $this->poster_id < 1
            || ! ($user = $this->c->users->load($this->poster_id)) instanceof User
        ) {
            $user = $this->c->users->guest([
                'username' => $this->poster,
                'email'    => $this->poster_email,
            ]);
        }

        if (! $user instanceof User) {
            throw new RuntimeException("No user data in post number {$this->id}");
        }

        return $user;
    }

    /**
     * Статус возможности сигналить на сообщение
     */
    protected function getcanReport(): bool
    {
        return ! $this->c->user->isAdmin && ! $this->c->user->isGuest;
    }

    /**
     * Ссылка на страницу отправки сигнала
     */
    protected function getlinkReport(): string
    {
        return $this->c->Router->link(
            'ReportPost',
            [
                'id' => $this->id,
            ]
        );
    }

    /**
     * Статус возможности удаления
     */
    protected function getcanDelete(): bool
    {
        if ($this->c->user->isAdmin) {
            return true;
        } elseif (
            $this->c->user->isGuest
            || isset($this->c->admins->list[$this->poster_id]) // ???? или юзера проверять?
        ) {
            return false;
        } elseif ($this->c->user->isModerator($this)) {
            return true;
        } elseif ('1' == $this->parent->closed) {
            return false;
        }

        return $this->user->id === $this->c->user->id
            && (
                (
                    $this->id == $this->parent->first_post_id
                    && 1 === $this->c->user->g_delete_topics
                )
                || (
                    $this->id != $this->parent->first_post_id
                    && 1 === $this->c->user->g_delete_posts
                )
            )
            && (
                '0' == $this->c->user->g_deledit_interval
                || '1' == $this->edit_post
                || \time() - $this->posted < $this->c->user->g_deledit_interval
            );
    }

    /**
     * Ссылка на страницу удаления
     */
    protected function getlinkDelete(): string
    {
        return $this->c->Router->link(
            'DeletePost',
            [
                'id' => $this->id,
            ]
        );
    }

    /**
     * Статус возможности редактирования
     */
    protected function getcanEdit(): bool
    {
        if ($this->c->user->isAdmin) {
            return true;
        } elseif (
            $this->c->user->isGuest
            || isset($this->c->admins->list[$this->poster_id]) // ???? или юзера проверять?
        ) {
            return false;
        } elseif ($this->c->user->isModerator($this)) {
            return true;
        } elseif ('1' == $this->parent->closed) {
            return false;
        }

        return $this->user->id === $this->c->user->id
            && 1 === $this->c->user->g_edit_posts
            && (
                '0' == $this->c->user->g_deledit_interval
                || '1' == $this->edit_post
                || \time() - $this->posted < $this->c->user->g_deledit_interval
                || (
                    $this->user->id === $this->editor_id
                    && \time() - $this->edited < $this->c->user->g_deledit_interval
                )
            );
    }

    /**
     * Ссылка на страницу редактирования
     */
    protected function getlinkEdit(): string
    {
        return $this->c->Router->link(
            'EditPost',
            [
                'id' => $this->id,
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
            'NewReply',
            [
                'id'    => $this->parent->id,
                'quote' => $this->id,
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
}
