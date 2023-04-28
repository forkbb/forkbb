<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Forum;

use ForkBB\Models\Manager;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Group\Group;
use RuntimeException;

class Forums extends Manager
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Forums';

    /**
     * Закешированные данные по разделам
     */
    protected array $forumList = [];

    /**
     * Создает новую модель раздела
     */
    public function create(array $attrs = []): Forum
    {
        return $this->c->ForumModel->setAttrs($attrs);
    }

    /**
     * Инициализация списка разделов
     * Обновляет кеш разделов
     */
    public function init(Group $group = null): Forums
    {
        if (null === $group) {
            $gid = $this->c->user->group_id;
        } else {
            $gid = $group->g_id;
        }

        $mark = $this->c->Cache->get('forums_mark');

        if (empty($mark)) {
            $mark = \time();

            if (true !== $this->c->Cache->set('forums_mark', $mark)) {
                throw new RuntimeException('Unable to write value to cache - forums_mark');
            }

            $result = [];
        } else {
            $result = $this->c->Cache->get("forums_{$gid}");
        }

        if (
            ! isset($result['time'], $result['list'])
            || $result['time'] < $mark
        ) {
            $result = [
                'time' => $mark,
                'list' => $this->refresh($group),
            ];

            if (true !== $this->c->Cache->set("forums_{$gid}", $result)) {
                throw new RuntimeException('Unable to write value to cache - forums_' . $gid);
            }
        }

        $this->forumList = $result['list'];

        return $this;
    }

    /**
     * Получение модели раздела
     */
    public function get(int|string $id): ?Forum
    {
        $forum = parent::get($id);

        if (! $forum instanceof Forum) {
            if (empty($this->forumList[$id])) {
                return null;
            }
            $forum = $this->create($this->forumList[$id]);
            $this->set($id, $forum);
        }

        return $forum;
    }

    /**
     * Обновляет раздел в БД
     */
    public function update(Forum $forum): Forum
    {
        return $this->Save->update($forum);
    }

    /**
     * Добавляет новый раздел в БД
     */
    public function insert(Forum $forum): int
    {
        $id = $this->Save->insert($forum);
        $this->set($id, $forum);

        return $id;
    }

    /**
     * Получение списка разделов и подразделов с указанием глубины вложения
     */
    public function depthList(Forum $forum, int $depth, array $list = []): array
    {
        ++$depth;
        foreach ($forum->subforums as $sub) {
            $sub->__depth = $depth;
            $list[]       = $sub;

            $list = $this->depthList($sub, $depth, $list);
        }

        return $list;
    }

    /**
     * Сбрасывает кеш
     */
    public function reset(): Forums
    {
        if (true !== $this->c->Cache->delete('forums_mark')) {
            throw new RuntimeException('Unable to remove key from cache - forums_mark');
        }

        return $this;
    }
}
