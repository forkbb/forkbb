<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Post\Model as Post;

class PreviousPost extends Action
{
    /**
     * Вычисляет номер сообщения перед указанным
     *
     * @param Post $post
     *
     * @return null|int
     */
    public function previousPost(Post $post): ?int
    {
        $vars = [
            ':pid' => $post->id,
            ':tid' => $post->topic_id,
        ];
        $sql = 'SELECT p.id
                FROM ::posts AS p
                WHERE p.id < ?i:pid AND p.topic_id=?i:tid
                ORDER BY p.id DESC
                LIMIT 1';
        $id = $this->c->DB->query($sql, $vars)->fetchColumn();

        return empty($id) ? null : $id;
    }
}
