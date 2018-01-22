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

        return implode(', ', $result);
    }

    protected function setData(array $args, array $data)
    {
        foreach ($args as $aliases => $model) {
            $attrs = [];
            foreach (explode('.', $aliases) as $alias) {
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
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     *
     * @return array
     */
    public function view($arg)
    {
        if ($arg instanceof Topic) {
            $expanded = false;
        } elseif ($arg instanceof Search) {
            $expanded = true;
        } else {
            throw new InvalidArgumentException('Expected Topic or Search');
        }

        if (empty($arg->idsList) || ! is_array($arg->idsList)) {
            throw new RuntimeException('Model does not contain of posts list for display');
        }

        $vars = [
            ':ids' => $arg->idsList,
        ];
        $sql = 'SELECT id, message, poster, posted
                FROM ::warnings
                WHERE id IN (?ai:ids)';
        $warnings = $this->c->DB->query($sql, $vars)->fetchAll(PDO::FETCH_GROUP);

        if (! $expanded) {
            $vars = [
                ':ids' => $arg->idsList,
                ':fields' => $this->queryFields([
                    'p' => array_map(function($val) {return true;}, $this->c->dbMap->posts), // все поля в true
                    'u' => array_map(function($val) {return true;}, $this->c->dbMap->users), // все поля в true
                    'g' => array_map(function($val) {return true;}, $this->c->dbMap->groups), // все поля в true
                ]),
            ];

            $sql = 'SELECT ?p:fields
                    FROM ::posts AS p
                    INNER JOIN ::users AS u ON u.id=p.poster_id
                    INNER JOIN ::groups AS g ON g.g_id=u.group_id
                    WHERE p.id IN (?ai:ids)';
        } else {

        }

        $stmt = $this->c->DB->query($sql, $vars);

        $result = array_flip($arg->idsList);

        while ($row = $stmt->fetch()) {
            $post = $this->manager->create();
            $user = $this->c->users->create();
            $this->setData(['p' => $post, 'u.g' => $user], $row);
            if (isset($warnings[$row['id']])) {
                $post->__warnings = $warnings[$row['id']];
            }
            $result[$post->id] = $post;
            if (! $this->c->users->get($user->id) instanceof User) {
                $this->c->users->set($user->id, $user);
            }
        }

        $offset    = ($arg->page - 1) * $this->c->user->disp_posts;
        $postCount = 0;
        $timeMax   = 0;

        if ($arg instanceof Topic) {
            foreach ($result as $post) {
                if ($post->posted > $timeMax) {
                    $timeMax = $post->posted;
                }
                if ($post->id === $arg->first_post_id && $offset > 0) {
                    $post->postNumber = 1;
                } else {
                    ++$postCount;
                    $post->postNumber = $offset + $postCount;
                }
            }
            $arg->timeMax = $timeMax;
        } else {
            foreach ($result as $post) {
                ++$postCount;
                $post->postNumber = $offset + $postCount; //????
            }
        }
        return $result;
    }
}
