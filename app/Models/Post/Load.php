<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\MethodModel;
use ForkBB\Models\Topic;

class Load extends MethodModel
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
                }

                if (true === $replName) {
                    $result[] = $name;
                    $aliases[$alias][$originalName] = true;
                    $fields[$originalName] = $alias;
                } else {
                    $result[] = $name . ' AS '. $replName;
                    $aliases[$alias][$replName] = $originalName;
                    $fields[$replName] = $alias;
                }
            }
        }
        $this->aliases = $aliases;

        return implode(', ', $result);
    }

    protected function setData(array $args, array $data)
    {
        foreach ($args as $alias => $model) {
            $attrs = [];
            foreach ($this->aliases[$alias] as $key => $repl) {
                $name = true === $repl ? $key : $repl;
                $attrs[$name] = $data[$key];
            }
            $model->setAttrs($attrs);
        }
    }

    /**
     * Заполняет модель данными из БД
     *
     * @param int $id
     * @param Topic $topic
     *
     * @return null|Post
     */
    public function load($id, Topic $topic = null)
    {
        // пост + топик
        if (null === $topic) {

            $fields = $this->queryFields([
                'p' => array_map(function($val) {return true;}, $this->c->dbMap->posts), // все поля в true
                't' => array_map(function($val) {return true;}, $this->c->dbMap->topics), // все поля в true
            ]);

            $vars = [
                ':pid' => $id,
                ':fields' => $fields,
            ];

            $sql = 'SELECT ?p:fields
                    FROM ::posts AS p 
                    INNER JOIN ::topics AS t ON t.id=p.topic_id
                    WHERE p.id=?i:pid';
        // только пост
        } else {
            $vars = [
                ':pid' => $id,
                ':tid' => $topic->id,
            ];

            $sql = 'SELECT p.* 
                    FROM ::posts AS p 
                    WHERE p.id=?i:pid AND p.topic_id=?i:tid';
        }

        $data = $this->c->DB->query($sql, $vars)->fetch();
                    
        // пост отсутствует или недоступен
        if (empty($data)) {
            return null;
        }

        if (null === $topic) {
            $topic = $this->c->ModelTopic;
            $this->setData(['p' => $this->model, 't' => $topic], $data);
        } else {
            $this->model->setAttrs($data);
        }
        $this->model->__parent = $topic;

        if ($topic->moved_to || ! $topic->parent) { //????
            return null;
        }
        
        return $this->model;
    }
}
