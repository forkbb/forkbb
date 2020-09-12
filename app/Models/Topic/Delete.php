<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\Action;
use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Models\User\Model as User;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет тему(ы)
     */
    public function delete(DataModel ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s), Forum(s) or Topic(s)');
        }

        $users        = [];
        $usersToGuest = [];
        $usersDel     = [];
        $usersUpd     = [];
        $forums       = [];
        $topics       = [];
        $parents      = [];
        $isUser       = 0;
        $isForum      = 0;
        $isTopic      = 0;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }
                if (true === $arg->deleteAllPost) {
                    $usersDel[] = $arg->id;
                } else {
                    $usersToGuest[] = $arg->id;
                }
                $users[] = $arg->id;
                $isUser  = 1;
            } elseif ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }
                $forums[$arg->id] = $arg;
                $isForum          = 1;
            } elseif ($arg instanceof Topic) {
                if (! $arg->parent instanceof Forum) {
                    throw new RuntimeException('Parent unavailable');
                }
                $topics[$arg->id]        = $arg;
                $parents[$arg->forum_id] = $arg->parent;
                $isTopic                 = 1;
            } else {
                throw new InvalidArgumentException('Expected User(s), Forum(s) or Topic(s)');
            }
        }

        if ($isUser + $isForum + $isTopic > 1) {
            throw new InvalidArgumentException('Expected only User(s), Forum(s) or Topic(s)');
        }

        if ($forums) {
            $vars  = [
                ':forums' => \array_keys($forums),
            ];
            $query = 'SELECT p.poster_id
                FROM ::topics AS t
                INNER JOIN ::posts AS p ON t.first_post_id=p.id
                WHERE t.forum_id IN (?ai:forums) AND t.moved_to=0
                GROUP BY p.poster_id';

            $usersUpd = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
        }
        if ($topics) {
            $vars  = [
                ':topics' => \array_keys($topics),
            ];
            $query = 'SELECT p.poster_id
                FROM ::topics AS t
                INNER JOIN ::posts AS p ON t.first_post_id=p.id
                WHERE t.id IN (?ai:topics) AND t.moved_to=0
                GROUP BY p.poster_id';

            $usersUpd = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
        }
        if ($usersDel) {
            $vars  = [
                ':users' => $usersDel,
            ];
            $query = 'SELECT t.id, t.forum_id
                FROM ::topics AS t
                INNER JOIN ::posts AS p ON t.first_post_id=p.id
                WHERE p.poster_id IN (?ai:users)';

            $topics = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_KEY_PAIR); //????

            if ($topics) {
                foreach ($topics as $value) { // ????
                    if (isset($parents[$value])) {
                        continue;
                    }
                    $parents[$value] = $this->c->forums->get($value);
                }
            }
        }

        $this->c->posts->delete(...$args);

        //???? опросы, предупреждения

        // удаление тем-ссылок на удаляемые темы

        if ($users) {
            $vars  = [
                ':users' => $users,
            ];
            $query = 'DELETE
                FROM ::mark_of_topic
                WHERE uid IN (?ai:users)';

            $this->c->DB->exec($query, $vars);
        }
        if ($usersToGuest) {
            $vars  = [
                ':users' => $usersToGuest,
            ];
            $query = 'UPDATE ::topics
                SET poster_id=1
                WHERE poster_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);

            $query = 'UPDATE ::topics
                SET last_poster_id=1
                WHERE last_poster_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);
        }
        if ($forums) {
            $vars  = [
                ':forums' => \array_keys($forums),
            ];
            $query = 'DELETE
                FROM ::mark_of_topic
                WHERE tid IN (
                    SELECT id
                    FROM ::topics
                    WHERE forum_id IN (?ai:forums)
                )';

            $this->c->DB->exec($query, $vars);

            $query = 'DELETE
                FROM ::topics
                WHERE forum_id IN (?ai:forums)';

            $this->c->DB->exec($query, $vars);
        }
        if ($topics) {
            $this->c->subscriptions->unsubscribe(...$topics);

            $vars  = [
                ':topics' => \array_keys($topics),
            ];
            $query = 'DELETE
                FROM ::mark_of_topic
                WHERE tid IN (?ai:topics)';

            $this->c->DB->exec($query, $vars);

            $query = 'DELETE
                FROM ::topics
                WHERE id IN (?ai:topics)';

            $this->c->DB->exec($query, $vars);

            foreach ($parents as $forum) {
                $this->c->forums->update($forum->calcStat());
            }
        }

        if ($usersUpd) {
            $this->c->users->UpdateCountTopics(...$usersUpd);
        }
    }
}
