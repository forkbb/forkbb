<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Topic\Topic;
use InvalidArgumentException;

class Load extends Action
{
    /**
     * Создает текст запрос
     */
    protected function getSql(string $where, bool $full): string
    {
        if ($this->c->user->isGuest) {
            $query = 'SELECT t.*
                FROM ::topics AS t
                WHERE ' . $where;
        } elseif ($full) {
            $query = 'SELECT t.*, s.user_id AS is_subscribed, mof.mf_mark_all_read, mot.mt_last_visit, mot.mt_last_read
                FROM ::topics AS t
                LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid)
                LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND t.forum_id=mof.fid)
                LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                WHERE ' . $where;
        } else {
            $query = 'SELECT t.*, mot.mt_last_visit, mot.mt_last_read
                FROM ::topics AS t
                LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                WHERE ' . $where;
        }

        return $query;
    }

    /**
     * Загружает тему из БД
     */
    public function load(int $id): ?Topic
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive topic id');
        }

        $vars = [
            ':tid' => $id,
            ':uid' => $this->c->user->id,
        ];
        $query = $this->getSql('t.id=?i:tid', true);

        $row = $this->c->DB->query($query, $vars)->fetch();

        // тема отсутствует или недоступна
        if (empty($row)) {
            return null;
        }

        $topic = $this->manager->create($row);
        $forum = $topic->parent;

        if ($forum instanceof Forum) {
            $forum->__mf_mark_all_read = $topic->mf_mark_all_read;

            return $topic;
        } else {
            return null;
        }
    }

    /**
     * Загружает список тем из БД
     */
    public function loadByIds(array $ids, bool $full): array
    {
        foreach ($ids as $id) {
            if (
                ! \is_int($id)
                || $id < 1
            ) {
                throw new InvalidArgumentException('Expected a positive topic id');
            }
        }

        $vars = [
            ':ids' => $ids,
            ':uid' => $this->c->user->id,
        ];
        $query = $this->getSql('t.id IN (?ai:ids)', $full);

        $stmt = $this->c->DB->query($query, $vars);

        $result = [];

        while ($row = $stmt->fetch()) {
            $topic = $this->manager->create($row);

            if ($topic->parent instanceof Forum) {
                $result[] = $topic;

                if (! empty($row['mf_mark_all_read'])) {
                    $topic->parent->__mf_mark_all_read = $row['mf_mark_all_read'];
                }
            }
        }

        return $result;
    }

    /**
     * Загружает список тем при открытие которых идет переадресация на тему c указанным id
     */
    public function loadLinks(int $id): array
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive topic id');
        }

        $vars = [
            ':id' => $id,
        ];
        $query = 'SELECT *
            FROM ::topics
            WHERE moved_to=?i:id
            ORDER BY id';

        $stmt = $this->c->DB->query($query, $vars);

        $result = [];

        while ($row = $stmt->fetch()) {
            $topic = $this->manager->create($row);

            if ($topic->parent instanceof Forum) {
                $result[] = $topic;
            }
        }

        return $result;
    }
}
