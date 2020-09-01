<?php

namespace ForkBB\Models\Subscription;

use ForkBB\Models\Model as ParentModel;
use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use PDOException;

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
     * Проверяет список моделей на форумы/темы
     * Заполняет $forums и $topics
     */
    protected function check(array $models): void
    {
        $this->forums = [];
        $this->topics = [];

        if (empty($models)) {
            throw new InvalidArgumentException('Expected at least one Forum or Topic');
        }

        foreach ($models as $model) {
            if ($model instanceof Forum) {
                $this->forums[$model->id] = $model->id;
            } elseif ($model instanceof Topic) {
                $this->topics[$model->id] = $model->id;
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
     * Отписывает юзера/всех юзеров от форума(ов)/тем(ы)
     */
    public function unsubscribe(?User $user, DataModel ...$models): bool
    {
        if ($user instanceof User) {
            if (
                $user->isGuest
                || $user->isUnverified
            ) {
                return false;
            }

            $vars = [
                ':uid' => $user->id,
            ];
        } else {
            $vars = [];
        }

        $this->check($models);

        if (! empty($this->forums)) {
            if (isset($vars[':uid'])) {
                $query = 'DELETE
                    FROM ::forum_subscriptions
                    WHERE user_id=?i:uid AND forum_id=?i:id';
            } else {
                $query = 'DELETE
                    FROM ::forum_subscriptions
                    WHERE forum_id=?i:id';
            }

            foreach ($this->forums as $id) {
                $vars[':id'] = $id;

                $this->c->DB->exec($query, $vars);
            }
        }

        if (! empty($this->topics)) {
            if (isset($vars[':uid'])) {
                $query = 'DELETE
                    FROM ::topic_subscriptions
                    WHERE user_id=?i:uid AND topic_id=?i:id';
            } else {
                $query = 'DELETE
                    FROM ::topic_subscriptions
                    WHERE topic_id=?i:id';
            }

            foreach ($this->topics as $id) {
                $vars[':id'] = $id;

                $this->c->DB->exec($query, $vars);
            }
        }

        return true;
    }
}
