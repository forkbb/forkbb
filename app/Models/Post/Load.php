<?php

declare(strict_types=1);

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Topic\Model as Topic;
use InvalidArgumentException;
use RuntimeException;

class Load extends Action
{
    /**
     * Создает текст запрос
     */
    protected function getSql(string $where): string
    {
        $query = 'SELECT p.*
            FROM ::posts AS p
            WHERE ' . $where;

        return $query;
    }

    /**
     * Загружает сообщение из БД с проверкой вхождения в указанную тему
     * Загружает сообщение из БД
     * Загружает тему этого сообщения в репозиторий topics
     * Проверка доступности
     */
    public function load(int $id, ?int $tid): ?Post
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive post id');
        }
        if (
            null !== $tid
            && $tid < 1
        ) {
            throw new InvalidArgumentException('Expected a positive topic id');
        }

        $vars  = [
            ':pid' => $id,
            ':tid' => $tid,
        ];
        $query = $this->getSql(null !== $tid ? 'p.id=?i:pid AND p.topic_id=?i:tid' : 'p.id=?i:pid');

        $data = $this->c->DB->query($query, $vars)->fetch();

        if (empty($data)) {
            return null;
        }

        $post  = $this->manager->create($data);
        $topic = $post->parent;

        if (! $topic instanceof Topic) {
            return null;
        }
        if (
            null !== $tid
            && $topic->id !== $tid
        ) {
            return null;
        }

        return $post;
    }

    /**
     * Загружает список сообщений из БД
     */
    public function loadByIds(array $ids, bool $withTopics): array
    {
        foreach ($ids as $id) {
            if (
                ! \is_int($id)
                || $id < 1
            ) {
                throw new InvalidArgumentException('Expected a positive topic id');
            }
        }

        $vars  = [
            ':ids' => $ids,
        ];
        $query = $this->getSql('p.id IN (?ai:ids)');

        $stmt = $this->c->DB->query($query, $vars);

        $result   = [];
        $topicIds = [];
        while ($row = $stmt->fetch()) {
            $post = $this->manager->create($row);
            $topicIds[$post->topic_id] = $post->topic_id;
            $result[] = $post;
        }

        if ($withTopics) {
            $this->c->topics->loadByIds($topicIds, true);
            foreach ($result as &$post) {
                if (! $post->parent instanceof Topic) {
                    $post = null;
                }
            }
            unset($post);
        } else {
            foreach ($topicIds as $id) {
                if (
                    ! $this->c->topics->isset($id)
                    || ! $this->c->topics->get($id) instanceof Topic
                ) {
                    throw new RuntimeException("Topic number {$id} not loaded");
                }
            }
        }

        return $result;
    }
}
