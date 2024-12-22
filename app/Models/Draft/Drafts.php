<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Draft;

use ForkBB\Models\Manager;
use ForkBB\Models\Draft\Draft;
use ForkBB\Models\Topic\Topic;
use PDO;
use InvalidArgumentException;

class Drafts extends Manager
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Drafts';

    /**
     * Создает новую модель черновика
     */
    public function create(array $attrs = []): Draft
    {
        return $this->c->DraftModel->setModelAttrs($attrs);
    }

    /**
     * Получает черновик по id
     */
    public function load(int $id): ?Draft
    {
        if ($this->isset($id)) {
            $draft = $this->get($id);

        } else {
            $draft = $this->Load->load($id);

            $this->set($id, $draft);
        }

        return $draft;
    }

    /**
     * Получает массив черновиков по ids
     */
    public function loadByIds(array $ids, bool $withTopics = true): array
    {
        $result = [];
        $data   = [];

        foreach ($ids as $id) {
            if ($this->isset($id)) {
                $result[$id] = $this->get($id);

            } else {
                $result[$id] = null;
                $data[]      = $id;

                $this->set($id, null);
            }
        }

        if (empty($data)) {
            return $result;
        }

        foreach ($this->Load->loadByIds($data, $withTopics) as $draft) {
            if ($draft instanceof Draft) {
                $result[$draft->id] = $draft;

                $this->set($draft->id, $draft);
            }
        }

        return $result;
    }

    /**
     * Обновляет черновик в БД
     */
    public function update(Draft $draft): Draft
    {
        return $this->Save->update($draft);
    }

    /**
     * Добавляет новый черновик в БД
     */
    public function insert(Draft $draft): int
    {
        $id = $this->Save->insert($draft);

        $this->set($id, $draft);

        return $id;
    }

    /**
     * Вычисляет количество черновиков текущего пользователя
     */
    public function count(): int
    {
        $vars = [
            ':uid' => $this->c->user->id,
        ];
        $query = 'SELECT COUNT(poster_id)
            FROM ::drafts
            WHERE poster_id=?i:uid';

        return (int) $this->c->DB->query($query, $vars)->fetchColumn();
    }

    /**
     * Возвращает список черновиков текущего пользователя со старницы $page
     */
    public function view(int $page): array
    {
        $offset = ($page - 1) * $this->c->user->disp_posts;

        $vars = [
            ':uid'    => $this->c->user->id,
            ':offset' => $offset,
            ':rows'   => $this->c->user->disp_posts,
        ];
        $query = 'SELECT d.id
            FROM ::drafts AS d
            WHERE d.poster_id=?i:uid
            ORDER BY d.id DESC
            LIMIT ?i:rows OFFSET ?i:offset';

        $ids = $this->c->DB->query($query, $vars)->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return [];
        }

        $result = $this->loadByIds($ids);

        foreach ($result as $draft) {
            ++$offset;

            if ($draft instanceof Draft) {
                $draft->__postNumber = $offset;
            }
        }

        return $result;
    }

    /**
     * Количество страниц в черновиках
     */
    public function numPages(): int
    {
        return (int) \ceil($this->c->user->num_drafts / $this->c->user->disp_posts);
    }

    /**
     * Перемещает черновики из тем ...$from в тему $to
     */
    public function move(Topic $to, Topic ...$from): void
    {
        if ($to->id < 1) {
            throw new InvalidArgumentException('Unexpected number of the recipient topic.');
        }

        $tids = [];

        foreach ($from as $topic) {
            $tids[$topic->id] = $topic->id;
        }

        $vars = [
            ':new'  => $to->id,
            ':tids' => $tids,
        ];
        $query = 'UPDATE ::drafts
            SET topic_id=?i:new
            WHERE topic_id IN (?ai:tids)';

        $this->c->DB->exec($query, $vars);
    }
}
