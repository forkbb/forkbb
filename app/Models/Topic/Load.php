<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;
use InvalidArgumentException;

class Load extends Action
{
    /**
     * Создает текст запрос
     */
    protected function getSql(string $where, bool $full): string
    {
        if ($this->c->user->isGuest) {
            $sql = 'SELECT t.*
                    FROM ::topics AS t
                    WHERE ' . $where;
        } elseif ($full) {
            $sql = 'SELECT t.*, s.user_id AS is_subscribed, mof.mf_mark_all_read, mot.mt_last_visit, mot.mt_last_read
                    FROM ::topics AS t
                    LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid)
                    LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND t.forum_id=mof.fid)
                    LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                    WHERE ' . $where;
        } else {
            $sql = 'SELECT t.*, mot.mt_last_visit, mot.mt_last_read
                    FROM ::topics AS t
                    LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                    WHERE ' . $where;
        }
        return $sql;
    }

    /**
     * Загружает тему из БД
     *
     * @param int $id
     *
     * @throws InvalidArgumentException
     *
     * @return null|Topic
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
        $sql  = $this->getSql('t.id=?i:tid', true);
        $data = $this->c->DB->query($sql, $vars)->fetch();

        // тема отсутствует или недоступна
        if (empty($data)) {
            return null;
        }

        $topic = $this->manager->create($data);
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
     *
     * @throws InvalidArgumentException
     */
    public function loadByIds(array $ids, bool $full): array
    {
        foreach ($ids as $id) {
            if (! \is_int($id) || $id < 1) {
                throw new InvalidArgumentException('Expected a positive topic id');
            }
        }

        $vars = [
            ':ids' => $ids,
            ':uid' => $this->c->user->id,
        ];
        $sql  = $this->getSql('t.id IN (?ai:ids)', $full);
        $stmt = $this->c->DB->query($sql, $vars);

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
}
