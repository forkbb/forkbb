<?php

declare(strict_types=1);

namespace ForkBB\Models\Subscription;

use ForkBB\Models\Model as ParentModel;
use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Models\User\Model as User;
use PDO;
use InvalidArgumentException;

class Model extends ParentModel
{
    /**
     * @var array
     */
    protected $forums;

    /**
     * @var array
     */
    protected $topics;

    /**
     * @var array
     */
    protected $users;

    /**
     * Проверяет список моделей на форумы/темы
     * Заполняет forums, topics и users
     */
    protected function check(array $models, bool $mayBeUsers = false): void
    {
        $this->forums = [];
        $this->topics = [];
        $this->users  = [];

        if (empty($models)) {
            if ($mayBeUsers) {
                throw new InvalidArgumentException('Expected at least one Forum, Topic or User');
            } else {
                throw new InvalidArgumentException('Expected at least one Forum or Topic');
            }
        }

        foreach ($models as $model) {
            if (
                $mayBeUsers
                && $model instanceof User
            ) {
                $this->users[$model->id] = $model->id;
            } elseif ($model instanceof Forum) {
                $this->forums[$model->id] = $model->id;
                $mayBeUsers               = false;
            } elseif ($model instanceof Topic) {
                $this->topics[$model->id] = $model->id;
                $mayBeUsers               = false;
            } else {
                throw new InvalidArgumentException('Expected only Forum or Topic');
            }
        }
    }

    /**
     * Подписывает юзера на форум(ы)/тему(ы)
     */
    public function subscribe(User $user, DataModel ...$models): bool
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

        if (! empty($this->forums)) {
            $query = 'INSERT INTO ::forum_subscriptions (user_id, forum_id)
                SELECT ?i:uid, ?i:id
                FROM ::groups
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM ::forum_subscriptions
                    WHERE user_id=?i:uid AND forum_id=?i:id
                )
                LIMIT 1';

            foreach ($this->forums as $id) {
                $vars[':id'] = $id;

                $this->c->DB->exec($query, $vars);
            }
        }

        if (! empty($this->topics)) {
            $query = 'INSERT INTO ::topic_subscriptions (user_id, topic_id)
                SELECT ?i:uid, ?i:id
                FROM ::groups
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM ::topic_subscriptions
                    WHERE user_id=?i:uid AND topic_id=?i:id
                )
                LIMIT 1';

            foreach ($this->topics as $id) {
                $vars[':id'] = $id;

                $this->c->DB->exec($query, $vars);
            }
        }

        return true;
    }

    /**
     * Отписывает юзеров от форумов/топиков
     * Убирает подписки с удаляемых форумов/топиков
     * Убирает подписки с удаляемых юзеров
     */
    public function unsubscribe(DataModel ...$models): bool
    {
        $where = [];
        $vars  = [];

        $this->check($models, true);

        if (! empty($this->users)) {
            if (1 === \count($this->users)) {
                $where[':uid'] = 'user_id=?i:uid';
                $vars[':uid']  = \reset($this->users);
            } else {
                $where[':uid'] = 'user_id IN(?ai:uid)';
                $vars[':uid']  = $this->users;
            }
        }

        $all = empty($this->forums) && empty($this->topics);

        if ($all || ! empty($this->forums)) {
            if (! empty($this->forums)) {
                if (1 === \count($this->forums)) {
                    $where[':id'] = 'forum_id=?i:id';
                    $vars[':id']  = \reset($this->forums);
                } else {
                    $where[':id'] = 'forum_id IN(?ai:id)';
                    $vars[':id']  = $this->forums;
                }
            }

            $query = 'DELETE
                FROM ::forum_subscriptions
                WHERE ' . \implode(' AND ', $where);

            $this->c->DB->exec($query, $vars);
        }

        unset($where[':id'], $vars[':id']);

        if ($all || ! empty($this->topics)) {
            if (! empty($this->topics)) {
                if (1 === \count($this->topics)) {
                    $where[':id'] = 'topic_id=?i:id';
                    $vars[':id']  = \reset($this->topics);
                } else {
                    $where[':id'] = 'topic_id IN(?ai:id)';
                    $vars[':id']  = $this->topics;
                }
            }

            $query = 'DELETE
                FROM ::topic_subscriptions
                WHERE ' . \implode(' AND ', $where);

            $this->c->DB->exec($query, $vars);
        }

        return true;
    }

    const FORUMS_DATA = 1;
    const TOPICS_DATA = 2;
    const ALL_DATA    = 3;

    /**
     * Возвращает информацию по подпискам
     */
    public function info(DataModel $model, int $type = self::ALL_DATA): array
    {
        $result = [];

        if ($model instanceof User) {
            $vars = [
                ':uid' => $model->id,
            ];

            if (self::FORUMS_DATA & $type) {
                if (
                    '1' != $this->c->config->o_forum_subscriptions
                    || $model->isGuest
                ) {
                    $result[self::FORUMS_DATA] = null;
                } else {
                    $query = 'SELECT forum_id
                        FROM ::forum_subscriptions
                        WHERE user_id=?i:uid';

                    $result[self::FORUMS_DATA] = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
                }
            }

            if (self::TOPICS_DATA & $type) {
                if (
                    '1' != $this->c->config->o_topic_subscriptions
                    || $model->isGuest
                ) {
                    $result[self::TOPICS_DATA] = null;
                } else {
                    $query = 'SELECT topic_id
                        FROM ::topic_subscriptions
                        WHERE user_id=?i:uid';

                    $result[self::TOPICS_DATA] = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
                }
            }
        } else {
            throw new InvalidArgumentException('Expected only User');
        }

        return $result;
    }
}
