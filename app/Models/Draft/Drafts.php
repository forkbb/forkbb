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
}
