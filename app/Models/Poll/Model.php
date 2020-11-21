<?php

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Core\Container;
use ForkBB\Models\DataModel;
use ForkBB\Models\Topic\Model as Topic;
use PDO;
use RuntimeException;

class Model extends DataModel
{
    /**
     * Возвращает родительскую тему
     */
    protected function getparent(): Topic
    {
        if ($this->tid < 1) {
            throw new RuntimeException('Parent is not defined');
        }

        $topic = $this->c->topics->get($this->tid);

        if (! $topic instanceof Topic) {
            throw new RuntimeException('Parent not found');
        }

        return $topic;
    }

    /**
     * Устанавливает родительскую тему
     */
    protected function setparent(Topic $topic): void
    {
        if ($topic->id < 1) {
            throw new RuntimeException('Parent has a bad id');
        }

        if (
            $this->tid > 0
            && $this->tid !== $topic->id
        ) {
            throw new RuntimeException('Alien parent');
        }

        $this->tid = $topic->id;
    }

    /**
     * Статус голосования для текущего пользователя
     */
    protected function getuserVoted(): bool
    {
        if ($this->c->user->isGuest) {
            return true;
        }

        if ($this->tid < 1) {
            return false;
        }

        $vars = [
            ':tid' => $this->tid,
            ':uid' => $this->c->user->id,
        ];
        $query = 'SELECT 1
            FROM ::poll_voted
            WHERE tid=?i:tid AND uid=?i:uid';

        return ! empty($this->c->DB->query($query, $vars)->fetch());
    }

    /**
     * Статус открытости
     */
    protected function getisOpen(): bool
    {
        return $this->tid < 1
            || (
                0 === $this->parent->closed
                && ($type = $this->parent->poll_type) > 0
                && (
                    1 === $type
                    || (
                        $type > 1000
                        && ($type - 1000) * 86400 > \time() - $this->parent->poll_time
                    )
                )
            );
    }

    /**
     * Статус возможности голосовать
     */
    protected function getcanVote(): bool
    {
        return $this->tid > 0
            && $this->c->user->usePoll
            && $this->isOpen
            && ! $this->userVoted;
    }

    /**
     * Статус возможности видеть результат
     */
    protected function getcanSeeResult(): bool
    {
        return (
                $this->c->user->usePoll
                || '1' == $this->c->config->b_poll_guest
            )
            && (
                0 === $this->parent->poll_term
                || $this->parent->poll_term < \max($this->total)
                || ! $this->isOpen
            );
    }

    /**
     * Статус возможности редактировать опрос
     */
    protected function getcanEdit(): bool
    {
        return $this->c->user->usePoll
            && (
                0 === $this->c->config->i_poll_time
                || $this->tid < 1
                || $this->c->user->isAdmin
                || (
                    $this->c->user->isAdmMod
                    && $this->c->user->isModerator($this)
                )
                || 60 * $this->c->config->i_poll_time > \time() - $this->parent->poll_time
            );
    }
}
