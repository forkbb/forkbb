<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Manager;
use ForkBB\Models\Post\Post;

class Posts extends Manager
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Posts';

    /**
     * Создает новую модель сообщения
     */
    public function create(array $attrs = []): Post
    {
        return $this->c->PostModel->setModelAttrs($attrs);
    }

    /**
     * Получает сообщение по id
     * Получает сообщение по id и tid темы (с проверкой вхождения)
     */
    public function load(int $id, ?int $tid = null): ?Post
    {
        if ($this->isset($id)) {
            $post = $this->get($id);

            if (
                $post instanceof Post
                && null !== $tid
                && $post->topic_id !== $tid
            ) {
                return null;
            }

        } else {
            $post = $this->Load->load($id, $tid);

            $this->set($id, $post);
        }

        return $post;
    }

    /**
     * Получает массив сообщений по ids
     */
    public function loadByIds(array $ids, bool $withTopics = true): array
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

        foreach ($this->Load->loadByIds($data, $withTopics) as $post) {
            if ($post instanceof Post) {
                $result[$post->id] = $post;

                $this->set($post->id, $post);
            }
        }

        return $result;
    }

    /**
     * Обновляет сообщение в БД
     */
    public function update(Post $post): Post
    {
        return $this->Save->update($post);
    }

    /**
     * Добавляет новое сообщение в БД
     */
    public function insert(Post $post): int
    {
        $id = $this->Save->insert($post);

        $this->set($id, $post);

        return $id;
    }
}
