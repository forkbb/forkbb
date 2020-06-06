<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Topic\Model as Topic;
use InvalidArgumentException;

class Load extends Action
{
    /**
     * Создает текст запрос
     */
    protected function getSql(string $where): string
    {
        $sql = 'SELECT p.*
                FROM ::posts AS p
                WHERE ' . $where;
        return $sql;
    }

    /**
     * Загружает сообщение из БД с проверкой вхождения в указанную тему
     * Загружает сообщение из БД
     * Загружает тему этого сообщения в репозиторий topics
     * Проверка доступности
     *
     * @param int $id
     * @param int $tid
     *
     * @throws InvalidArgumentException
     *
     * @return null|Post
     */
    public function load(int $id, ?int $tid): ?Post
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive post id');
        }
        if (null !== $tid && $tid < 1) {
            throw new InvalidArgumentException('Expected a positive topic id');
        }

        $vars = [
            ':pid' => $id,
            ':tid' => $tid,
        ];
        $sql  = $this->getSql(
            null !== $tid ?
            'p.id=?i:pid AND p.topic_id=?i:tid' :
            'p.id=?i:pid'
        );
        $data = $this->c->DB->query($sql, $vars)->fetch();

        if (empty($data)) {
            return null;
        }

        $post  = $this->manager->create($data);
        $topic = $post->parent;

        if (! $topic instanceof Topic || $topic->moved_to || ! $topic->parent) {
            return null;
        }
        if (null !== $tid && $topic->id !== $tid) {
            return null;
        }

        return $post;
    }
}
