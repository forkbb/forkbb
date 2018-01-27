<?php

namespace ForkBB\Models\Topic;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\Topic\Model as Topic;
use RuntimeException;

class Manager extends ManagerModel
{
    /**
     * Создает новую модель темы
     *
     * @param array $attrs
     *
     * @return Topic
     */
    public function create(array $attrs = [])
    {
        return $this->c->TopicModel->setAttrs($attrs);
    }

    /**
     * Загружает тему из БД
     *
     * @param int $id
     *
     * @return null|Topic
     */
    public function load($id)
    {
        $topic = $this->get($id);

        if (! $topic instanceof Topic) {
            $topic = $this->Load->load($id);
            $this->set($id, $topic);
        }

        return $topic;
    }

    /**
     * Обновляет тему в БД
     *
     * @param Topic $topic
     *
     * @return Topic
     */
    public function update(Topic $topic)
    {
        return $this->Save->update($topic);
    }

    /**
     * Добавляет новую тему в БД
     *
     * @param Topic $topic
     *
     * @return int
     */
    public function insert(Topic $topic)
    {
        $id = $this->Save->insert($topic);
        $this->set($id, $topic);
        return $id;
    }
}
