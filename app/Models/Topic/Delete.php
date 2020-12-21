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

        $uids        = [];
        $uidsToGuest = [];
        $uidsDelete  = [];
        $uidsUpdate  = [];
        $forums      = [];
        $topics      = [];
        $parents     = [];
        $isUser      = 0;
        $isForum     = 0;
        $isTopic     = 0;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }

                if (true === $arg->deleteAllPost) {
                    $uidsDelete[$arg->id] = $arg->id;
                } else {
                    $uidsToGuest[$arg->id] = $arg->id;
                }

                $uids[$arg->id] = $arg->id;
                $isUser         = 1;
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
                $topics[$arg->id]          = $arg;
                $parents[$arg->parent->id] = $arg->parent;
                $isTopic                   = 1;
            } else {
                throw new InvalidArgumentException('Expected User(s), Forum(s) or Topic(s)');
            }
        }

        if ($isUser + $isForum + $isTopic > 1) {
            throw new InvalidArgumentException('Expected only User(s), Forum(s) or Topic(s)');
        }

        if ($forums) {
            $vars = [
                ':forums' => \array_keys($forums),
            ];
            $query = 'SELECT t.id
                FROM ::topics AS t
                WHERE t.forum_id IN (?ai:forums)';

            $tids   = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
            $topics = $this->manager->loadByIds($tids, false);
        }

        if ($topics) {
            foreach ($topics as $topic) {
                $uidsUpdate[$topic->poster_id] = $topic->poster_id;
            }
        }

        if ($uidsDelete) {
            $vars = [
                ':users' => $uidsDelete,
            ];
            $query = 'SELECT t.id
                FROM ::topics AS t
                WHERE t.poster_id IN (?ai:users)';

            $tids   = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);
            $topics = $this->manager->loadByIds($tids, false);

            foreach ($topics as $topic) {
                $parents[$topic->parent->id] = $topic->parent;
            }
        }

        $this->c->posts->delete(...$args);

        if ($uids) {
            $vars = [
                ':users' => $uids,
            ];
            $query = 'DELETE
                FROM ::mark_of_topic
                WHERE uid IN (?ai:users)';

            $this->c->DB->exec($query, $vars);
        }

        if ($uidsToGuest) {
            $vars = [
                ':users' => $uidsToGuest,
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

        if ($topics) {
            if (isset($topics[0])) { // O_o
                throw new RuntimeException('Bad topic');
            }

            $this->c->subscriptions->unsubscribe(...$topics);
            $this->c->polls->delete(...$topics);

            $vars = [
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

            $query = 'DELETE
                FROM ::topics
                WHERE moved_to IN (?ai:topics)';

            $this->c->DB->exec($query, $vars);
        }

        if ($parents) {
            foreach ($parents as $forum) {
                $this->c->forums->update($forum->calcStat());
            }
        }

        if ($uidsUpdate) {
            $this->c->users->UpdateCountTopics(...$uidsUpdate);
        }
    }
}
