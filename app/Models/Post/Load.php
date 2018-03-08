<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\Action;

class Load extends Action
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
     * Загружает сообщение из БД с проверкой вхождения в указанную тему
     * Проверка доступности
     *
     * @param int $id
     * @param int $tid
     *
     * @return null|Post
     */
    public function loadFromTopic($id, $tid)
    {
        $vars = [
            ':pid' => $id,
            ':tid' => $tid,
        ];
        $sql = 'SELECT p.*
                FROM ::posts AS p
                WHERE p.id=?i:pid AND p.topic_id=?i:tid';

        $data = $this->c->DB->query($sql, $vars)->fetch();

        // сообщение отсутствует или недоступено
        if (empty($data)) {
            return null;
        }

        $post  = $this->manager->create($data);
        $topic = $post->parent;

        if (empty($topic) || $topic->moved_to || ! $topic->parent) {
            return null;
        }

        return $post;
    }

    /**
     * Загружает сообщение из БД
     * Загружает тему этого сообщения
     * Проверка доступности
     *
     * @param int $id
     *
     * @return null|Post
     */
    public function load($id)
    {
        if ($this->c->user->isGuest) {
            $vars = [
                ':pid' => $id,
                ':fields' => $this->queryFields([
                    'p' => \array_map(function($val) {return true;}, $this->c->dbMap->posts), // все поля в true
                    't' => \array_map(function($val) {return true;}, $this->c->dbMap->topics), // все поля в true
                ]),
            ];

            $sql = 'SELECT ?p:fields
                    FROM ::posts AS p
                    INNER JOIN ::topics AS t ON t.id=p.topic_id
                    WHERE p.id=?i:pid';
        } else {
            $vars = [
                ':pid' => $id,
                ':uid' => $this->c->user->id,
                ':fields' => $this->queryFields([
                    'p'   => \array_map(function($val) {return true;}, $this->c->dbMap->posts), // все поля в true
                    't'   => \array_map(function($val) {return true;}, $this->c->dbMap->topics), // все поля в true
                    's'   => ['user_id' => 'is_subscribed'],
                    'mof' => ['mf_mark_all_read' => true],
                    'mot' => ['mt_last_visit' => true, 'mt_last_read' => true],
                ]),
            ];

            $sql = 'SELECT ?p:fields
                    FROM ::posts AS p
                    INNER JOIN ::topics AS t ON t.id=p.topic_id
                    LEFT JOIN ::topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id=?i:uid)
                    LEFT JOIN ::mark_of_forum AS mof ON (mof.uid=?i:uid AND t.forum_id=mof.fid)
                    LEFT JOIN ::mark_of_topic AS mot ON (mot.uid=?i:uid AND t.id=mot.tid)
                    WHERE p.id=?i:pid';
        }

        $data = $this->c->DB->query($sql, $vars)->fetch();

        // сообщение отсутствует или недоступено
        if (empty($data)) {
            return null;
        }

        $post  = $this->manager->create();
        $topic = $this->c->topics->create();
        $this->setData(['p' => $post, 't.s.mof.mot' => $topic], $data);

        if ($topic->moved_to || ! $topic->parent) {
            return null;
        }

        $topic->parent->__mf_mark_all_read = $topic->mf_mark_all_read; //????

        $this->c->topics->set($topic->id, $topic);

        return $post;
    }
}
