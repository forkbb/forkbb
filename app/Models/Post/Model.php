<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Model extends DataModel
{
    /**
     * Получение родительского раздела
     *
     * @throws RuntimeException
     *
     * @return Topic|null
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
     *
     * @return string
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
     *
     * @throws RuntimeException
     *
     * @return User\Model
     */
    protected function getuser(): User //????
    {
        $user = $this->c->users->load($this->poster_id);

        if (! $user instanceof User) {
            throw new RuntimeException('No user data in post number ' . $this->id);
        } elseif (1 === $this->poster_id) {
            $user = clone $user;

            $user->setAttr('email_normal', false); // заблокировать вычисление в модели User

            $user->__email        = $this->poster_email;
            $user->__username     = $this->poster;
        }

        return $user;
    }

    protected function getcanReport(): bool
    {
        return ! $this->c->user->isAdmin && ! $this->c->user->isGuest;
    }

    protected function getlinkReport(): string
    {
        return $this->c->Router->link(
            'ReportPost',
            [
                'id' => $this->id,
            ]
        );
    }

    protected function getcanDelete(): bool
    {
        if ($this->c->user->isGuest) {
            return false;
        } elseif (
            $this->c->user->isAdmin
            || (
                $this->c->user->isModerator($this)
                && ! $this->user->isAdmin
            )
        ) {
            return true;
        } elseif ('1' == $this->parent->closed) {
            return false;
        }

        return $this->user->id === $this->c->user->id
            && (
                (
                    $this->id == $this->parent->first_post_id
                    && '1' == $this->c->user->g_delete_topics
                )
                || (
                    $this->id != $this->parent->first_post_id
                    && '1' == $this->c->user->g_delete_posts
                )
            )
            && (
                '0' == $this->c->user->g_deledit_interval
                || '1' == $this->edit_post
                || \time() - $this->posted < $this->c->user->g_deledit_interval
            );
    }

    protected function getlinkDelete(): string
    {
        return $this->c->Router->link(
            'DeletePost',
            [
                'id' => $this->id,
            ]
        );
    }

    protected function getcanEdit(): bool
    {
        if ($this->c->user->isGuest) {
            return false;
        } elseif (
            $this->c->user->isAdmin
            || (
                $this->c->user->isModerator($this)
                && ! $this->user->isAdmin
            )
        ) {
            return true;
        } elseif ('1' == $this->parent->closed) {
            return false;
        }

        return $this->user->id === $this->c->user->id
            && '1' == $this->c->user->g_edit_posts
            && (
                '0' == $this->c->user->g_deledit_interval
                || '1' == $this->edit_post
                || \time() - $this->posted < $this->c->user->g_deledit_interval
            );
    }

    protected function getlinkEdit(): string
    {
        return $this->c->Router->link(
            'EditPost',
            [
                'id' => $this->id,
            ]
        );
    }

    protected function getcanQuote(): bool
    {
        return $this->parent->canReply;
    }

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
     *
     * @return string
     */
    public function html(): string
    {
        return $this->c->censorship->censor($this->c->Parser->parseMessage($this->message, (bool) $this->hide_smilies));
    }
}
