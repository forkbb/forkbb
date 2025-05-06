<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
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
use InvalidArgumentException;
use RuntimeException;

class Load extends Method
{
    /**
     * Создает текст запрос
     */
    protected function getSql(int $type, bool $solo = true): string
    {

        switch ($type) {
            case Cnst::PTOPIC:
                $where = $solo ? 'pt.id=?i:tid' : 'pt.id IN (?ai:ids)';

                return "SELECT * FROM ::pm_topics AS pt WHERE {$where}";
            case Cnst::PPOST:
                $where = $solo ? 'pp.id=?i:pid' : 'pp.id IN (?ai:ids)';

                return "SELECT * FROM ::pm_posts AS pp WHERE  {$where}";
            default:
                throw new InvalidArgumentException("Unknown request type: {$type}");
        }
    }

    public function load(int $type, int $id): ?DataModel
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

        $vars  = [
            ':tid' => $id,
        ];
        $query = $this->getSql(Cnst::PTOPIC);
        $data  = $this->c->DB->query($query, $vars)->fetch();

        // тема отсутствует
        if (empty($data)) {
            return null;
        }

        $topic = $this->model->create(Cnst::PTOPIC, $data);

        $this->c->users->loadByIds([$topic->poster_id, $topic->target_id]);

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

        $vars  = [
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

        $vars    = [
            ':ids' => $ids,
        ];
        $query   = $this->getSql(Cnst::PTOPIC, false);
        $stmt    = $this->c->DB->query($query, $vars);
        $result  = [];
        $userIds = [];

        while ($row = $stmt->fetch()) {
            $result[] = $this->model->create(Cnst::PTOPIC, $row);

            $userIds[$row['poster_id']] = $row['poster_id'];
            $userIds[$row['target_id']] = $row['target_id'];
        }

        $this->c->users->loadByIds($userIds);

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

        $vars   = [
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
