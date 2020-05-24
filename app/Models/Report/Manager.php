<?php

namespace ForkBB\Models\Report;

use ForkBB\Models\ManagerModel;
use ForkBB\Models\Report\Model as Report;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Manager extends ManagerModel
{
    /**
     * Создает новую модель сообщения
     *
     * @param array $attrs
     *
     * @return Report
     */
    public function create(array $attrs = []): Report
    {
        return $this->c->ReportModel->setAttrs($attrs);
    }

    /**
     * Загружает сообщение из БД
     *
     * @param int $id
     * @param int $tid
     *
     * @return null|Report
     */
    public function load(int $id, int $tid = null): ?Report
    {
        $post = $this->get($id);

        if ($post instanceof Report) {
            if (null !== $tid && $post->topic_id !== $tid) {
                return null;
            }
        } else {
            if (null !== $tid) {
                $post = $this->Load->loadFromTopic($id, $tid);
            } else {
                $post = $this->Load->load($id);
            }
            $this->set($id, $post);
        }

        return $post;
    }

    /**
     * Обновляет сообщение в БД
     *
     * @param Report $post
     *
     * @return Report
     */
    public function update(Report $post): Report
    {
        return $this->Save->update($post);
    }

    /**
     * Добавляет новое сообщение в БД
     *
     * @param Report $post
     *
     * @return int
     */
    public function insert(Report $post): int
    {
        $id = $this->Save->insert($post);
        $this->set($id, $post);
        return $id;
    }

    /**
     * Id последнего репорта
     *
     * @return int
     */
    public function lastId(): int
    {
        if ($this->c->Cache->has('report')) {
            $last = $this->list = $this->c->Cache->get('report');
        } else {
            $last = (int) $this->c->DB->query('SELECT r.id FROM ::reports AS r ORDER BY r.id DESC LIMIT 1')->fetchColumn();

            $this->c->Cache->set('report', $last);
        }

        return $last;
    }
}
