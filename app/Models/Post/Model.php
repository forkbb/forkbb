<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\DataModel;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Model extends DataModel
{
    /**
     * Получение родительского раздела
     *
     * @throws RuntimeException
     *
     * @return Topic
     */
    protected function getparent()
    {
        if ($this->topic_id < 1) {
            throw new RuntimeException('Parent is not defined');
        }

        return $this->c->topics->get($this->topic_id);
    }

    /**
     * Ссылка на сообщение
     *
     * @return string
     */
    protected function getlink()
    {
        return $this->c->Router->link('ViewPost', ['id' => $this->id]);
    }

    /**
     * Автор сообщения
     *
     * @throws RuntimeException
     *
     * @return User
     */
    protected function getuser() //????
    {
        $user = $this->c->users->load($this->poster_id);

        if (! $user instanceof User) {
            throw new RuntimeException('No user data in post number ' . $this->id);
        } elseif (1 === $this->poster_id) {
            $user = clone $user;
            $user->__email = $this->poster_email;
            $user->__username = $this->poster;
        }

        return $user;
    }

    protected function getcanReport()
    {
        return ! $this->c->user->isAdmin && ! $this->c->user->isGuest;
    }

    protected function getlinkReport()
    {
        return $this->c->Router->link('ReportPost', ['id' => $this->id]);
    }

    protected function getcanDelete()
    {
        if ($this->c->user->isGuest) {
            return false;
        } elseif ($this->c->user->isAdmin || ($this->c->user->isModerator($this) && ! $this->user->isAdmin)) {
            return true;
        } elseif ($this->parent->closed == '1') {
            return false;
        }

        return $this->user->id === $this->c->user->id
            && (($this->id == $this->parent->first_post_id && $this->c->user->g_delete_topics == '1')
                || ($this->id != $this->parent->first_post_id && $this->c->user->g_delete_posts == '1')
            )
            && ($this->c->user->g_deledit_interval == '0'
                || $this->edit_post == '1'
                || \time() - $this->posted < $this->c->user->g_deledit_interval
            );
    }

    protected function getlinkDelete()
    {
        return $this->c->Router->link('DeletePost', ['id' => $this->id]);
    }

    protected function getcanEdit()
    {
        if ($this->c->user->isGuest) {
            return false;
        } elseif ($this->c->user->isAdmin || ($this->c->user->isModerator($this) && ! $this->user->isAdmin)) {
            return true;
        } elseif ($this->parent->closed == '1') {
            return false;
        }

        return $this->user->id === $this->c->user->id
            && $this->c->user->g_edit_posts == '1'
            && ($this->c->user->g_deledit_interval == '0'
                || $this->edit_post == '1'
                || \time() - $this->posted < $this->c->user->g_deledit_interval
            );
    }

    protected function getlinkEdit()
    {
        return $this->c->Router->link('EditPost', ['id' => $this->id]);
    }

    protected function getcanQuote()
    {
        return $this->parent->canReply;
    }

    protected function getlinkQuote()
    {
        return $this->c->Router->link('NewReply', ['id' => $this->parent->id, 'quote' => $this->id]);
    }

    /**
     * HTML код сообщения
     *
     * @return string
     */
    public function html()
    {
        return $this->c->censorship->censor($this->c->Parser->parseMessage($this->message, (bool) $this->hide_smilies));
    }
}
