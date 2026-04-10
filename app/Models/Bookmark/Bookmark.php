<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Bookmark;

use ForkBB\Models\Model;
use ForkBB\Models\DataModel;
use ForkBB\Models\Topic\Topic;
use ForkBB\Models\User\User;
use PDO;
use InvalidArgumentException;

class Bookmark extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Bookmark';

    protected array $topics;
    protected array $users;

    /**
     * Проверяет список моделей на разделы/темы
     * Заполняет topics и users
     */
    protected function check(array $models, bool $mayBeUsers = false): void
    {
        $this->topics = [];
        $this->users  = [];

        if (empty($models)) {
            if ($mayBeUsers) {
                throw new InvalidArgumentException('Expected at least one Topic or User');

            } else {
                throw new InvalidArgumentException('Expected at least one Topic');
            }
        }

        foreach ($models as $model) {
            if (
                $mayBeUsers
                && $model instanceof User
            ) {
                $this->users[$model->id] = $model->id;

            } elseif ($model instanceof Topic) {
                $this->topics[$model->id] = $model->id;
                $mayBeUsers               = false;

            } else {
                throw new InvalidArgumentException('Expected only Topic');
            }
        }
    }

    /**
     * Добавляет тему(ы) в закладки пользователя
     */
    public function bookmark(User $user, DataModel ...$models): bool
    {
        if (
            $user->isGuest
            || $user->isUnverified
        ) {
            return false;
        }

        $this->check($models);

        $vars = [
            ':uid' => $user->id,
        ];

        if (! empty($this->topics)) {
            $query = 'INSERT INTO ::topic_bookmarks (user_id, topic_id)
                SELECT tmp.*
                FROM (SELECT ?i:uid AS f1, ?i:id AS f2) AS tmp
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM ::topic_bookmarks
                    WHERE user_id=?i:uid AND topic_id=?i:id
                )';

            foreach ($this->topics as $id) {
                $vars[':id'] = $id;

                $this->c->DB->exec($query, $vars);
            }
        }

        return true;
    }

    /**
     * Убирает темы из закладок пользователей
     * Очищает закладки при удалении пользователей/тем
     */
    public function unbookmark(DataModel ...$models): bool
    {
        $where = [];
        $vars  = [];

        $this->check($models, true);

        if (! empty($this->users)) {
            if (1 === \count($this->users)) {
                $where[':uid'] = 'user_id=?i:uid';
                $vars[':uid']  = \reset($this->users);

            } else {
                $where[':uid'] = 'user_id IN (?ai:uid)';
                $vars[':uid']  = $this->users;
            }
        }

        if (! empty($this->topics)) {
            if (1 === \count($this->topics)) {
                $where[':id'] = 'topic_id=?i:id';
                $vars[':id']  = \reset($this->topics);

            } else {
                $where[':id'] = 'topic_id IN (?ai:id)';
                $vars[':id']  = $this->topics;
            }
        }

        $query = 'DELETE
            FROM ::topic_bookmarks
            WHERE ' . \implode(' AND ', $where);

        $this->c->DB->exec($query, $vars);

        return true;
    }

    /**
     * Возвращает информацию по закладкам
     */
    public function info(User $user): array
    {
        if ($user->group_id === $this->c->user->group_id) {
            $root = $this->c->forums->get(0);

        } else {
            $root = $this->c->ForumManager->init($this->c->groups->get($user->group_id))->get(0);
        }

        $vars = [
            ':uid' => $user->id,
            ':fid' => \array_keys($root->descendants),
        ];
        $query = 'SELECT t.id
            FROM ::topics AS t
            INNER JOIN ::topic_bookmarks AS tbm ON (tbm.user_id=?i:uid AND tbm.topic_id=t.id)
            WHERE t.moved_to=0 AND t.forum_id IN (?ai:fid)
            ORDER BY t.last_post DESC'; // ???? оптимизация?

        return $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
    }
}
