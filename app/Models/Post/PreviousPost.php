<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Post\Post;

class PreviousPost extends Action
{
    /**
     * Вычисляет номер сообщения перед указанным или его время публикации
     */
    public function previousPost(Post $post, bool $returnId = true): ?int
    {
        $vars = [
            ':pid' => $post->id,
            ':tid' => $post->topic_id,
        ];

        if ($returnId) {
            $query = 'SELECT MAX(p.id)
                FROM ::posts AS p
                WHERE p.id < ?i:pid AND p.topic_id=?i:tid';

        } else {
            $query = 'SELECT p.posted
                FROM ::posts AS p
                WHERE p.id < ?i:pid AND p.topic_id=?i:tid
                ORDER BY p.id DESC
                LIMIT 1';
        }

        $id = (int) $this->c->DB->query($query, $vars)->fetchColumn();

        return $id ?: null;
    }
}
