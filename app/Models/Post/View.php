<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;
use ForkBB\Models\Search\Model as Search;
use ForkBB\Models\Topic\Model as Topic;
use ForkBB\Models\User\Model as User;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class View extends Action
{
    protected $aliases;

    protected function queryFields(array $args)
    {
        $result  = [];
        $fields  = [];
        $aliases = [];
        foreach ($args as $alias => $rawFields) {
            $aliases[$alias] = [];
            foreach ($rawFields as $originalName => $replName) {
                if (null === $replName || false === $replName) {
                    continue;
                }

                $name = $alias . '.' . $originalName;

                if (true === $replName && isset($fields[$originalName])) {
                    $replName = "alias_{$alias}_{$originalName}";
                    $result[] = $name . ' AS '. $replName;
                    $aliases[$alias][$replName] = $originalName;
                    $fields[$replName] = $alias;
                } elseif (true === $replName) {
                    $result[] = $name;
                    $aliases[$alias][$originalName] = true;
                    $fields[$originalName] = $alias;
                } else {
                    $result[] = $name . ' AS '. $replName;
                    $aliases[$alias][$replName] = $replName; //???? $originalName;
                    $fields[$replName] = $alias;
                }
            }
        }
        $this->aliases = $aliases;

        return \implode(', ', $result);
    }

    protected function setData(array $args, array $data)
    {
        foreach ($args as $aliases => $model) {
            $attrs = [];
            foreach (\explode('.', $aliases) as $alias) {
                if (empty($this->aliases[$alias])) {
                    continue;
                }
                foreach ($this->aliases[$alias] as $key => $repl) {
                    $name = true === $repl ? $key : $repl;
                    $attrs[$name] = $data[$key];
                }
            }
            $model->setAttrs($attrs);
        }
    }

    /**
     * Возвращает список сообщений
     *
     * @param mixed $arg
     * @param bool $review
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     *
     * @return array
     */
    public function view($arg, $review = false)
    {
        if (! $arg instanceof Topic && ! $arg instanceof Search) {
            throw new InvalidArgumentException('Expected Topic or Search');
        }

        if (empty($arg->idsList) || ! \is_array($arg->idsList)) {
            throw new RuntimeException('Model does not contain of posts list for display');
        }

        if (! $review) {
            $vars = [
                ':ids' => $arg->idsList,
            ];
            $sql = 'SELECT w.id, w.message, w.poster, w.posted
                    FROM ::warnings AS w
                    WHERE w.id IN (?ai:ids)';
            $warnings = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_GROUP);
        }

        $userIds = [];
        $result  = \array_flip($arg->idsList);

        if ($arg instanceof Topic) {
            $vars = [
                ':ids' => $arg->idsList,
            ];
            $sql = 'SELECT p.*
                    FROM ::posts AS p
                    WHERE p.id IN (?ai:ids)';
            $stmt = $this->c->DB->query($sql, $vars);

            while ($row = $stmt->fetch()) {
                $post = $this->manager->create($row);

                if (isset($warnings[$row['id']])) {
                    $post->__warnings = $warnings[$row['id']];
                }

                $userIds[$post->poster_id] = true;

                $result[$post->id] = $post;
            }
        } else {
            if ($this->c->user->isGuest) {
                $vars = [
                    ':ids' => $arg->idsList,
                    ':fields' => $this->queryFields([
                        'p'   => \array_map(function($val) {return true;}, $this->c->dbMap->posts), // все поля в true
                        't'   => \array_map(function($val) {return true;}, $this->c->dbMap->topics), // все поля в true
                    ]),
                ];
                $sql = 'SELECT ?p:fields
                        FROM ::posts AS p
                        INNER JOIN ::topics AS t ON t.id=p.topic_id
                        WHERE p.id IN (?ai:ids)';

            } else {
                $vars = [
                    ':ids' => $arg->idsList,
                    ':uid' => $this->c->user->id,
                    ':fields' => $this->queryFields([
                        'p'   => \array_map(function($val) {return true;}, $this->c->dbMap->posts), // все поля в true
                        't'   => \array_map(function($val) {return true;}, $this->c->dbMap->topics), // все поля в true
#                        's'   => ['user_id' => 'is_subscribed'],
                        'mof' => ['mf_mark_all_read' => true],
                        'mot' => ['mt_last_visit' => true, 'mt_last_read' => true],
                    ]),
                ];
                $sql = 'SELECT ?p:fields
                        FROM ::posts AS p
                        INNER JOIN ::topics AS t ON t.id=p.topic_id
                        LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND t.forum_id=mof.fid)
                        LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                        WHERE p.id IN (?ai:ids)';
#                        LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid)
            }

            $stmt = $this->c->DB->query($sql, $vars);

            while ($row = $stmt->fetch()) {

                $post  = $this->manager->create();
                $topic = $this->c->topics->create();
                $this->setData(['p' => $post, 't.s.mof.mot' => $topic], $row);

                if (isset($warnings[$row['id']])) {
                    $post->__warnings = $warnings[$row['id']];
                }

                $userIds[$post->poster_id] = true;

                $result[$post->id] = $post;

                if (! $this->c->topics->get($topic->id)) {
                    $this->c->topics->set($topic->id, $topic);
                }
            }
        }

        $this->c->users->load(\array_keys($userIds));

        $offset    = ($arg->page - 1) * $this->c->user->disp_posts;
        $timeMax   = 0;
        if ($review) {
            $postCount = $arg->num_replies + 2;
            $sign      = -1;
        } else {
            $postCount = 0;
            $sign      = 1;
        }

        if ($arg instanceof Topic) {
            foreach ($result as $post) {
                if ($post->posted > $timeMax) {
                    $timeMax = $post->posted;
                }
                if ($post->id === $arg->first_post_id && $offset > 0) {
                    if (empty($post->id)) {
                        continue;
                    }
                    $post->__postNumber = 1;
                } else {
                    $postCount += $sign;
                    if (empty($post->id)) {
                        continue;
                    }
                    $post->__postNumber = $offset + $postCount;
                }
            }
            $arg->timeMax = $timeMax;
        } else {
            foreach ($result as $post) {
                ++$postCount;
                if (empty($post->id)) {
                    continue;
                }
                $post->__postNumber = $offset + $postCount; //????
            }
        }
        return $result;
    }
}
