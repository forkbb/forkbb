<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Core\Container;
use ForkBB\Models\DataModel;
use ForkBB\Models\Topic\Topic;
use PDO;
use RuntimeException;
use function \ForkBB\__;

class Poll extends DataModel
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Poll';

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
     * Ссылка на обработчик опроса
     */
    protected function getlink(): ?string
    {
        if ($this->tid > 0) {
            return $this->c->Router->link('Poll', ['tid' => $this->tid]);

        } else {
            return null;
        }
    }

    /**
     * Значение токена для формы голосования
     */
    protected function gettoken(): ?string
    {
        if ($this->tid > 0) {
            return $this->c->Csrf->create('Poll', ['tid' => $this->tid]);

        } else {
            return null;
        }
    }

    /**
     * Статус голосования для текущего пользователя
     */
    protected function getuserVoted(): bool
    {
        if (
            $this->c->user->isGuest
            || $this->tid < 1
        ) {
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
            && $this->c->userRules->usePoll
            && $this->isOpen
            && ! $this->userVoted;
    }

    /**
     * Статус возможности видеть результат
     */
    protected function getcanSeeResult(): bool
    {
        return (
                $this->c->userRules->usePoll
                || 1 === $this->c->config->b_poll_guest
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
        return $this->c->userRules->usePoll
            && (
                0 === $this->c->config->i_poll_time
                || $this->tid < 1
                || $this->c->user->isAdmin
                || $this->c->user->isModerator($this)
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

    /**
     * Возвращает статус опроса для текущего пользователя или null
     */
    protected function getstatus(): string|array|null
    {
        if ($this->tid < 1) {
            return null;

        } elseif (
            $this->c->user->isGuest
            && 1 !== $this->c->config->b_poll_guest
        ) {
            return 'Poll results are hidden from the guests';

        } elseif (! $this->isOpen) {
            return 'This poll is closed';

        } elseif (! $this->canSeeResult) {
            return ['Poll results are hidden up to %s voters', $this->parent->poll_term];

        } elseif ($this->userVoted) {
            return 'You voted';

        } elseif ($this->c->user->isGuest) {
            return 'Guest cannot vote';

        } else {
            return 'Poll status is undefined';
        }
    }

    /**
     * Голосование
     * Возвращает текст ошибки или null
     */
    public function vote(array $vote): ?string
    {
        if (! $this->canVote) {
            return __('You cannot vote on this poll');
        }

        $data = [];

        foreach (\array_keys($this->question) as $q) {
            if ($this->type[$q] > 1) {
                $count = \count($vote[$q]);

                if (0 == $count) {
                    return __(['No vote on question %s', $q]);

                } elseif ($count > $this->type[$q]) {
                    return __(['Too many answers selected in question %s', $q]);
                }

                foreach (\array_keys($vote[$q]) as $a) {
                    if (! isset($this->answer[$q][$a])) {
                        return __(['The selected answer is not present in question %s', $q]);
                    }

                    $data[] = [$q, $a];
                }

            } else {
                if (! isset($vote[$q][0])) {
                    return __(['No vote on question %s', $q]);

                } elseif (! isset($this->answer[$q][$vote[$q][0]])) {
                    return __(['The selected answer is not present in question %s', $q]);
                }

                $data[] = [$q, $vote[$q][0]];
            }

            $data[] = [$q, 0];
        }

        $vars = [
            ':tid' => $this->tid,
            ':uid' => $this->c->user->id,
            ':rez' => \json_encode($data, FORK_JSON_ENCODE),
        ];
        $query = 'INSERT INTO ::poll_voted (tid, uid, rez)
            VALUES (?i:tid, ?i:uid, ?s:rez)';

        $this->c->DB->exec($query, $vars);

        $vars = [
            ':tid' => $this->tid,
        ];
        $query = 'UPDATE ::poll
            SET votes=votes+1
            WHERE tid=?i:tid AND question_id=?i:qid AND field_id=?i:fid';

        foreach ($data as list($vars[':qid'], $vars[':fid'])) {
            $this->c->DB->exec($query, $vars);
        }

        $this->c->polls->reset($this->tid);

        return null;
    }
}
