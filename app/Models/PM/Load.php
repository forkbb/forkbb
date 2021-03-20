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
use ForkBB\Models\Model as ParentModel;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use InvalidArgumentException;
use RuntimeException;

class Load extends Method
{
    /**
     * @var array
     */
    protected $userIds;

    /**
     * Создает текст запрос
     */
    protected function getSql(int $type, bool $solo = true): string
    {

        switch ($type) {
            case Cnst::PRND:
                $where = $solo ? 'pr.topic_id=?i:tid' : 'pr.topic_id IN (?ai:ids)';

                return "SELECT * FROM pm_rnd AS pr WHERE {$where}";
            case Cnst::PTOPIC:
                $where = $solo ? 'pt.id=?i:tid' : 'pt.id IN (?ai:ids)';

                return "SELECT * FROM pm_topics AS pt WHERE {$where}";
            case Cnst::PPOST:
                $where = $solo ? 'pp.id=?i:pid' : 'pp.id IN (?ai:ids)';

                return "SELECT * FROM pm_posts AS pp WHERE  {$where}";
            default:
                throw new RuntimeException('Unknown request type');
        }
    }

    /**
     * Группирует данные пользователей по приватным тема (можно было и в PDO получить, но...)
     * и выбирает все id пользователей
     */
    protected function calc(array $data): array
    {
        $this->userIds = [];
        $result        = [];

        foreach ($data as $row) {
            $uid = $row['user_id'];
            $tid = $row['topic_id'];

            unset($row['topic_id']);

            $this->userIds[$uid] = $uid;

            if (empty($result[$tid])) {
                $result[$tid] = [];
            }

            $result[$tid][$row['user_number']] = $row;
        }

        return $result;
    }

    public function load(int $type, int $id): ?ParentModel
    {
        switch ($type) {
            case Cnst::PTOPIC:
                return $this->loadTopic($id);
            case Cnst::PPOST:
                return $this->loadPost($id);
            default:
                return null;
        }
    }

    public function loadByIds(int $type, array $ids): array
    {
        switch ($type) {
            case Cnst::PTOPIC:
                return $this->loadTopics($ids);
            case Cnst::PPOST:
                return $this->loadPosts($ids);
            default:
                return [];
        }
    }

    /**
     * Загружает приватную тему из БД
     */
    protected function loadTopic(int $id): ?PTopic
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive ptopic id');
        }

        $vars = [
            ':tid' => $id,
        ];
        $query = $this->getSql(Cnst::PTOPIC);
        $data  = $this->c->DB->query($query, $vars)->fetch();

        // тема отсутствует
        if (empty($data)) {
            return null;
        }

        $topic      = $this->model->create(Cnst::PTOPIC, $data);
        $query      = $this->getSql(Cnst::PRND);
        $dataU      = $this->calc($this->c->DB->query($query, $vars)->fetchAll());
        $rnd        = $this->model->create(Cnst::PRND);
        $rnd->list  = $dataU[$id];
        $topic->rnd = $rnd;

        $this->c->users->loadByIds($this->userIds);

        return $topic;
    }

    /**
     * Загружает приватное сообщение из БД
     */
    protected function loadPost(int $id): ?PPost
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Expected a positive ppost id');
        }

        $vars = [
            ':pid' => $id,
        ];
        $query = $this->getSql(Cnst::PPOST);
        $data  = $this->c->DB->query($query, $vars)->fetch();

        if (empty($data)) {
            return null;
        } else {
            return $this->model->create(Cnst::PPOST, $data);
        }
    }

    /**
     * Загружает список приватных тем из БД
     */
    protected function loadTopics(array $ids): array
    {
        foreach ($ids as $id) {
            if (
                ! \is_int($id)
                || $id < 1
            ) {
                throw new InvalidArgumentException('Expected a positive ptopic id');
            }
        }

        $vars = [
            ':ids' => $ids,
        ];
        $query = $this->getSql(Cnst::PRND, false);
        $dataU = $this->calc($this->c->DB->query($query, $vars)->fetchAll());

        $this->c->users->loadByIds($this->userIds);

        $query  = $this->getSql(Cnst::PTOPIC, false);
        $stmt   = $this->c->DB->query($query, $vars);
        $result = [];

        while ($row = $stmt->fetch()) {
            $topic      = $this->model->create(Cnst::PTOPIC, $row);
            $rnd        = $this->model->create(Cnst::PRND);
            $rnd->list  = $dataU[$row['id']];
            $topic->rnd = $rnd;
            $result[]   = $topic;
        }

        return $result;
    }

    /**
     * Загружает список приватных сообщений из БД
     */
    protected function loadPosts(array $ids): array
    {
        foreach ($ids as $id) {
            if (
                ! \is_int($id)
                || $id < 1
            ) {
                throw new InvalidArgumentException('Expected a positive ppost id');
            }
        }

        $vars = [
            ':ids' => $ids,
        ];
        $query  = $this->getSql(Cnst::PPOST, false);
        $stmt   = $this->c->DB->query($query, $vars);
        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $this->model->create(Cnst::PPOST, $row);
        }

        return $result;
    }
}
