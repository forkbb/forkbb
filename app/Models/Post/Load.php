<?php

namespace ForkBB\Models\Post;

use ForkBB\Models\MethodModel;
use ForkBB\Models\Topic;

class Load extends MethodModel
{
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
        $vars = [
            ':pid' => $id,
        ];
        $where = 'p.id=?i:pid';

        if ($topic) {
            $vars[':tid'] = $topic->id;
            $where       .= ' AND p.topic_id=?i:tid';
        }

        $sql = 'SELECT p.* FROM ::posts AS p WHERE ' . $where;

        $data = $this->c->DB->query($sql, $vars)->fetch();

        // пост отсутствует или недоступен
        if (empty($data)) {
            return null;
        }

        $this->model->setAttrs($data);
        if ($topic) {
            $this->model->__parent = $topic;
        }

        return $this->model;
    }
}
