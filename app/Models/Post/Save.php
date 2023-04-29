<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Post\Post;
use RuntimeException;

class Save extends Action
{
    /**
     * Обновляет сообщение в БД
     */
    public function update(Post $post): Post
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
            $set[]  = $name . '=?' . $fileds[$name];
        }

        if (empty($set)) {
            return $post;
        }

        $vars[] = $post->id;

        $set   = \implode(', ', $set);
        $query = "UPDATE ::posts
            SET {$set}
            WHERE id=?i";

        $this->c->DB->exec($query, $vars);
        $post->resModified();

        return $post;
    }

    /**
     * Добавляет новое сообщение в БД
     */
    public function insert(Post $post): int
    {
        if (null !== $post->id) {
            throw new RuntimeException('The model has ID');
        }

        $attrs  = $post->getAttrs();
        $fileds = $this->c->dbMap->posts;
        $set = $set2 = $vars = [];

        foreach ($attrs as $key => $value) {
            if (! isset($fileds[$key])) {
                continue;
            }

            $vars[] = $value;
            $set[]  = $key;
            $set2[] = '?' . $fileds[$key];
        }

        if (empty($set)) {
            throw new RuntimeException('The model is empty');
        }

        $set   = \implode(', ', $set);
        $set2  = \implode(', ', $set2);
        $query = "INSERT INTO ::posts ({$set})
            VALUES ({$set2})";

        $this->c->DB->exec($query, $vars);
        $post->id = (int) $this->c->DB->lastInsertId();
        $post->resModified();

        return $post->id;
    }
}
