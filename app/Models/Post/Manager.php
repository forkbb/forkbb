<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\Post\Model as Post;

class Manager extends ManagerModel
{
    /**
     * Создает новую модель сообщения
     *
     * @param array $attrs
     *
     * @return Post
     */
    public function create(array $attrs = []): Post
    {
        return $this->c->PostModel->setAttrs($attrs);
    }

    /**
     * Получает сообщение по id
     * Получает сообщение по id и tid темы (с проверкой вхождения)
     *
     * @param int $id
     * @param int $tid
     *
     * @return null|Post
     */
    public function load(int $id, int $tid = null): ?Post
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
     *
     * @param Post $post
     *
     * @return Post
     */
    public function update(Post $post): Post
    {
        return $this->Save->update($post);
    }

    /**
     * Добавляет новое сообщение в БД
     *
     * @param Post $post
     *
     * @return int
     */
    public function insert(Post $post): int
    {
        $id = $this->Save->insert($post);
        $this->set($id, $post);
        return $id;
    }
}
