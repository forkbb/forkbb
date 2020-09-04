<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Post\Model as Post;

class PreviousPost extends Action
{
    /**
     * Вычисляет номер сообщения перед указанным или его время публикации
     *
     * @param Post $post
     *
     * @return null|int
     */
    public function previousPost(Post $post, $returnId = true): ?int
    {
        $vars  = [
            ':pid' => $post->id,
            ':tid' => $post->topic_id,
        ];
        $field = $returnId ? 'id' : 'posted';
        $query = "SELECT p.{$field}
            FROM ::posts AS p
            WHERE p.id < ?i:pid AND p.topic_id=?i:tid
            ORDER BY p.id DESC
            LIMIT 1";

        $id = $this->c->DB->query($query, $vars)->fetchColumn();

        return empty($id) ? null : $id;
    }
}
