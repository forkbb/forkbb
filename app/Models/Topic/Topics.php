<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Topic;

use ForkBB\Models\Manager;
use ForkBB\Models\Topic\Topic;

class Topics extends Manager
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Topics';

    /**
     * Создает новую модель темы
     */
    public function create(array $attrs = []): Topic
    {
        return $this->c->TopicModel->setModelAttrs($attrs);
    }

    /**
     * Получает тему по id
     */
    public function load(int $id): ?Topic
    {
        if ($this->isset($id)) {
            return $this->get($id);

        } else {
            $topic = $this->Load->load($id);

            $this->set($id, $topic);

            return $topic;
        }
    }

    /**
     * Получает массив тем по ids
     */
    public function loadByIds(array $ids, bool $full = true): array
    {
        $result = [];
        $data   = [];

        foreach ($ids as $id) {
            if ($this->isset($id)) {
                $result[$id] = $this->get($id);

            } else {
                $result[$id] = null;
                $data[]      = $id;

                $this->set($id, null);
            }
        }

        if (empty($data)) {
            return $result;
        }

        foreach ($this->Load->loadByIds($data, $full) as $topic) {
            if ($topic instanceof Topic) {
                $result[$topic->id] = $topic;

                $this->set($topic->id, $topic);
            }
        }

        return $result;
    }

    /**
     * Получает список тем при открытие которых идет переадресация на текущую тему
     */
    public function loadLinks(int|Topic $arg): array
    {
        $id     = \is_int($arg) ? $arg : (int) $arg->id;
        $result = [];

        foreach ($this->Load->loadLinks($id) as $topic) {
            if ($this->isset($topic->id)) {
                $result[$topic->id] = $this->get($topic->id);

            } else {
                $result[$topic->id] = $topic;

                $this->set($topic->id, $topic);
            }
        }

        return $result;
    }

    /**
     * Обновляет тему в БД
     */
    public function update(Topic $topic): Topic
    {
        return $this->Save->update($topic);
    }

    /**
     * Добавляет новую тему в БД
     */
    public function insert(Topic $topic): int
    {
        $id = $this->Save->insert($topic);

        $this->set($id, $topic);

        return $id;
    }
}
