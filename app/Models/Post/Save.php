<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Post\Model as Post;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет сообщение в БД
     *
     * @param Post $post
     * 
     * @throws RuntimeException
     * 
     * @return Post
     */
    public function update(Post $post)
    {
        if ($post->id < 1) {
            throw new RuntimeException('The model does not have ID');
        }
        $modified = $post->getModified();
        if (empty($modified)) {
            return $post;
        }
        $values = $post->getAttrs();
        $fileds = $this->c->dbMap->posts;
        $set = $vars = [];
        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }
            $vars[] = $values[$name];
            $set[] = $name . '=?' . $fileds[$name];
        }
        if (empty($set)) {
            return $post;
        }
        $vars[] = $post->id;
        $this->c->DB->query('UPDATE ::posts SET ' . implode(', ', $set) . ' WHERE id=?i', $vars);
        $post->resModified();

        return $post;
    }

    /**
     * Добавляет новое сообщение в БД
     *
     * @param Post $post
     * 
     * @throws RuntimeException
     * 
     * @return int
     */
    public function insert(Post $post)
    {
        $modified = $post->getModified();
        if (null !== $post->id || in_array('id', $modified)) {
            throw new RuntimeException('The model has ID');
        }
        $values = $post->getAttrs();
        $fileds = $this->c->dbMap->posts;
        $set = $set2 = $vars = [];
        foreach ($modified as $name) {
            if (! isset($fileds[$name])) {
                continue;
            }
            $vars[] = $values[$name];
            $set[] = $name;
            $set2[] = '?' . $fileds[$name];
        }
        if (empty($set)) {
            throw new RuntimeException('The model is empty');
        }
        $this->c->DB->query('INSERT INTO ::posts (' . implode(', ', $set) . ') VALUES (' . implode(', ', $set2) . ')', $vars);
        $post->id = $this->c->DB->lastInsertId();
        $post->resModified();

        return $post->id;
    }
}
