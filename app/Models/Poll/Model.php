<?php

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Core\Container;
use ForkBB\Models\DataModel;
use ForkBB\Models\Topic\Model as Topic;
use PDO;
use RuntimeException;
use function \ForkBB\__;

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

    /**
     * Возвращает максимум голосов за один ответ по каждому вопросу
     */
    protected function getmaxVote(): array
    {
        $result = [];

        foreach (\array_keys($this->question) as $q) {
            $result[$q] = \min(\max($this->vote[$q]), $this->total[$q]);
        }

        return $result;
    }

    /**
     * Возвращает процент голосов по каждому ответу кажого вопроса
     */
    protected function getpercVote(): array
    {
        $result = [];

        foreach (\array_keys($this->question) as $q) {
            if ($this->total[$q] > 0) {
                $total = $this->total[$q] / 100;

                foreach (\array_keys($this->answer[$q]) as $a) {
                    $result[$q][$a] = \min(100, \round($this->vote[$q][$a] / $total, 2));
                }
            } else {
                foreach (\array_keys($this->answer[$q]) as $a) {
                    $result[$q][$a] = 0;
                }
            }
        }

        return $result;
    }

    /**
     * Возвращает ширину ответа в процентах
     */
    protected function getwidthVote(): array
    {
        $result = [];

        foreach (\array_keys($this->question) as $q) {
            if ($this->maxVote[$q] > 0) {
                $max = $this->maxVote[$q] / 100;

                foreach (\array_keys($this->answer[$q]) as $a) {
                    $result[$q][$a] = \min(100, \round($this->vote[$q][$a] / $max));
                }
            } else {
                foreach (\array_keys($this->answer[$q]) as $a) {
                    $result[$q][$a] = 0;
                }
            }
        }

        return $result;
    }

    protected function getstatus(): ?string
    {
        if ($this->tid < 1) {
            return null;
        } elseif (
            $this->c->user->isGuest
            && '1' != $this->c->config->b_poll_guest
        ) {
            return __('Poll results are hidden from the guests');
        } elseif (! $this->isOpen) {
            return __('This poll is closed');
        } elseif (! $this->canSeeResult) {
            return __('Poll results are hidden up to %s voters', $this->parent->poll_term);
        } elseif ($this->userVoted) {
            return __('You voted');
        } else {
            return __('Poll status is undefined');
        }
    }
}
