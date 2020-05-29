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
     * Загружает сообщение из БД
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

            if ($post instanceof Post && null !== $tid && $post->topic_id !== $tid) {
                return null;
            }
        } else {
            if (null !== $tid) {
                $post = $this->Load->loadFromTopic($id, $tid);
            } else {
                $post = $this->Load->load($id);
            }
            $this->set($id, $post);
        }

        return $post;
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
