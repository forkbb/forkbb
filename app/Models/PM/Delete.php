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
use ForkBB\Models\User\User;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Method
{
    protected function deletePTopics(array $ids): void
    {
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

            $this->deletePTopics($ids);
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
            $vars = [
                ':ids'    => \array_keys($users),
                ':status' => Cnst::PT_DELETED,
            ];
            $query = 'DELETE
                FROM ::pm_block
                WHERE bl_first_id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);

            $query = 'DELETE
                FROM ::pm_block
                WHERE bl_second_id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);

            $query = 'UPDATE ::pm_topics
                SET poster_id=1, poster_status=?i:status
                WHERE poster_id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);

            $query = 'UPDATE ::pm_topics
                SET target_id=1, target_status=?i:status
                WHERE target_id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);

            $query = 'UPDATE ::pm_posts
                SET poster_id=1
                WHERE poster_id IN (?ai:ids)';

            $this->c->DB->exec($query, $vars);

            $vars = [
                ':st1' => Cnst::PT_DELETED,
                ':st2' => Cnst::PT_NOTSENT,
            ];
            $query = 'SELECT id, poster_id, target_id
                FROM ::pm_topics
                WHERE (poster_status=?i:st1 AND target_status=?i:st1)
                   OR (poster_status=?i:st1 AND target_status=?i:st2)
                   OR (poster_status=?i:st2 AND target_status=?i:st1)
                   OR (poster_status=?i:st2 AND target_status=?i:st2)';

            $stmt = $this->c->DB->query($query, $vars);
            $ids  = [];
            $uids = [];

            while ($row = $stmt->fetch()) {
                $ids[$row['id']]         = $row['id'];
                $uids[$row['poster_id']] = $row['poster_id'];
                $uids[$row['target_id']] = $row['target_id'];
            }

            $this->deletePTopics($ids);

            unset($uids[1]);

            foreach ($this->c->users->loadByIds($uids) as $user) {
                if ($user instanceof User) {
                    $calcUsers[$user->id] = $user;
                }
            }
        }

        foreach ($calcUsers as $user) {
            $this->model->recalculate($user);
        }
    }
}
