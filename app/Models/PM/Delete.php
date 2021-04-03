<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\PM;

use ForkBB\Models\Method;
use ForkBB\Models\DataModel;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Method
{
    /**
     * Удаляет тему(ы)
     */
    public function delete(DataModel ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s), PPost(s) or PTopic(s)');
        }

        $users     = [];
        $posts     = [];
        $topics    = [];
        $isUser    = 0;
        $isPost    = 0;
        $isTopic   = 0;
        $calcUsers = [];

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }

                $users[$arg->id] = $arg;
                $isUser         = 1;
            } elseif ($arg instanceof PPost) {
                if (! $arg->parent instanceof PTopic) {
                    throw new RuntimeException('Bad ppost');
                }

                $posts[$arg->id] = $arg;
                $isPost          = 1;
            } elseif ($arg instanceof PTopic) {
                if (! $this->model->accessTopic($arg->id)) {
                    throw new RuntimeException('Bad ptopic');
                }

                $topics[$arg->id] = $arg;
                $isTopic          = 1;
            } else {
                throw new InvalidArgumentException('Expected User(s), PPost(s) or PTopic(s)');
            }
        }

        if ($isUser + $isPost + $isTopic > 1) {
            throw new InvalidArgumentException('Expected only User(s), PPost(s) or PTopic(s)');
        }

        if ($topics) {
            $ids = [];

            foreach ($topics as $topic) {
                $calcUsers[$topic->zpUser->id] = $topic->zpUser;

                if ($topic->isFullDeleted) {
                    $ids[] = $topic->id;
                } else {
                    $this->model->update(Cnst::PTOPIC, $topic);
                }
            }

            if ($ids) {
                $vars = [
                    ':ids' => $ids,
                ];
                $query = 'DELETE
                    FROM ::pm_topics
                    WHERE id IN (?ai:ids)';

                $this->c->DB->exec($query, $vars);

                $query = 'DELETE
                    FROM ::pm_posts
                    WHERE topic_id IN (?ai:ids)';

                $this->c->DB->exec($query, $vars);
            }
        }

        if ($posts) {
            $calcTopics = [];

            foreach ($posts as $post) {
                $topic = $post->parent;
                $calcTopics[$topic->id] = $topic;

                if ($topic->last_post_id === $post->id) {
                    $calcUsers[$topic->ztUser->id] = $topic->ztUser;
                }
            }

            $vars = [
                ':ids' => \array_keys($posts),
            ];
            $query = 'DELETE
                FROM ::pm_posts
                WHERE id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);

            foreach ($calcTopics as $topic) {
                $this->model->update(Cnst::PTOPIC, $topic->calcStat());
            }
        }

        if ($users) {

        }

        foreach ($calcUsers as $user) {
            $this->model->recalculate($user);
        }
    }
}
