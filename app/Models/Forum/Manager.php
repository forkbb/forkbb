<?php

namespace ForkBB\Models\Forum;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Group\Model as Group;
use RuntimeException;

class Manager extends ManagerModel
{
    /**
     * Закешированные данные по разделам
     * @var array
     */
    protected $forumList = [];

    /**
     * Создает новую модель раздела
     */
    public function create(array $attrs = []): Forum
    {
        return $this->c->ForumModel->setAttrs($attrs);
    }

    /**
     * Инициализация списка разделов
     */
    public function init(Group $group = null): Manager
    {
        if (null === $group) {
            $gid = $this->c->user->group_id;
        } else {
            $gid = $group->g_id;
        }

        $mark = $this->c->Cache->get('forums_mark');
        if (empty($mark)) {
            $this->c->Cache->set('forums_mark', \time());
            $list = $this->refresh($group);
        } else {
            $result = $this->c->Cache->get('forums_' . $gid);
            if (
                empty($result['time'])
                || $result['time'] < $mark
            ) {
                $list = $this->refresh($group);
            } else {
                $list = $result['list'];
            }
        }

        $this->forumList = $list;

        return $this;
    }

    /**
     * Получение модели раздела
     */
    public function get($id): ?Forum
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
    public function reset(): Manager
    {
        $this->c->Cache->delete('forums_mark');

        return $this;
    }
}
