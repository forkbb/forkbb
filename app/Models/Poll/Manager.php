<?php

declare(strict_types=1);

namespace ForkBB\Models\Poll;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\Poll\Model as Poll;
use RuntimeException;

class Manager extends ManagerModel
{
    /**
     * Создает новый опрос
     */
    public function create(array $attrs = []): Poll
    {
        return $this->c->PollModel->setAttrs($attrs);
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
                $data = $poll instanceof Poll ? $poll->getAttrs() : null; // ????

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
            $this->reset($poll->id);
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
    public function reset(int $id): Manager
    {
        if (true !== $this->c->Cache->delete("poll{$id}")) {
            throw new RuntimeException("Unable to remove key from cache - poll{$id}");
        }

        return $this;
    }
}
