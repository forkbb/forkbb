<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Draft;

use ForkBB\Models\Action;
use ForkBB\Models\Draft\Draft;
use ForkBB\Models\Topic\Topic;
use InvalidArgumentException;
use RuntimeException;

class Load extends Action
{
    /**
     * Загружает черновик из БД
     * Загружает тему этого черновика в репозиторий topics (????)
     * Проверка доступности
     */
    public function load(int $id): ?Draft
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive draft id');
        }

        $vars  = [
            ':did' => $id,
        ];
        $query = 'SELECT * FROM ::drafts WHERE id=?i:did';
        $data  = $this->c->DB->query($query, $vars)->fetch();

        if (empty($data)) {
            return null;
        }

        $draft = $this->manager->create($data);
        $topic = $draft->parent;

        return $topic instanceof Topic ? $draft : null;
    }

    /**
     * Загружает список черновиков из БД
     */
    public function loadByIds(array $ids, bool $withTopics): array
    {
        foreach ($ids as $id) {
            if (
                ! \is_int($id)
                || $id < 1
            ) {
                throw new InvalidArgumentException('Expected a positive draft id');
            }
        }

        $vars  = [
            ':ids' => $ids,
        ];
        $query = 'SELECT * FROM ::drafts WHERE id IN (?ai:ids)';
        $stmt  = $this->c->DB->query($query, $vars);

        $result   = [];
        $topicIds = [];

        while ($row = $stmt->fetch()) {
            $draft                      = $this->manager->create($row);
            $topicIds[$draft->topic_id] = $draft->topic_id;
            $result[]                   = $draft;
        }

        unset($topicIds[0]);

        if ($withTopics) {
            $this->c->topics->loadByIds($topicIds, true);

            foreach ($result as &$draft) {
                if (! $draft->parent instanceof Topic) {
                    $draft = null;
                }
            }

            unset($draft);

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
