<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Models\Manager;
use ForkBB\Models\Poll\Poll;
use RuntimeException;

class Polls extends Manager
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Polls';

    /**
     * Создает новый опрос
     */
    public function create(array $attrs = []): Poll
    {
        return $this->c->PollModel->setModelAttrs($attrs);
    }

    /**
     * Получает опрос по id
     */
    public function load(int $id): ?Poll
    {
        if ($this->isset($id)) {
            return $this->get($id);

        } else {
            $data = $this->c->Cache->get("poll{$id}", false);

            if (null === $data) {
                $poll = null;

            } elseif (\is_array($data)) {
                $poll = $this->create($data);

            } else {
                $poll = $this->Load->load($id);
                $data = $poll instanceof Poll ? $poll->getModelAttrs() : null; // ????

                $this->c->Cache->set("poll{$id}", $data);
            }

            $this->set($id, $poll);

            return $poll;
        }
    }

    /**
     * Обновляет опрос в БД
     */
    public function update(Poll $poll): Poll
    {
        $poll = $this->Save->update($poll);

        if (true === $poll->itWasModified) {
            $this->reset($poll->tid);
        }

        return $poll;
    }

    /**
     * Добавляет новый опрос в БД
     */
    public function insert(Poll $poll): int
    {
        $id = $this->Save->insert($poll);

        $this->set($id, $poll);

        return $id;
    }

    /**
     * Сбрасывает кеш указанного голосования
     */
    public function reset(int $id): Polls
    {
        if (true !== $this->c->Cache->delete("poll{$id}")) {
            throw new RuntimeException("Unable to remove key from cache - poll{$id}");
        }

        return $this;
    }
}
