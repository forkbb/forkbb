<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Draft;

use ForkBB\Models\DataModel;
use PDO;
use RuntimeExceptio;

class Premod extends DataModel
{
    const CACHE_KEY = 'premod';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Premod';

    public function init(): Premod
    {
        $this->setModelAttrs([]);

        $admin = $this->c->user->isAdmin;
        $list  = [];
        $count = [];

        if (! $admin) {
            $this->fidsForMod = $fids = $this->c->forums->fidsForMod($this->c->user->id);
        }

        $query = 'SELECT d.id, d.forum_id as fid, t.forum_id as tfid
            FROM ::drafts AS d
            LEFT JOIN ::topics AS t ON t.id=d.topic_id
            WHERE d.pre_mod=1
            ORDER BY d.id';

        $stmt = $this->c->DB->query($query);

        while (false !== ($row = $stmt->fetch())) {
            $fid = $row['fid'] ?: $row['tfid'];

            $count[$fid] ??= 0;
            ++$count[$fid];

            if (
                $admin
                || isset($fids[$fid])
            ) {
                $list[] = $row['id'];
            }
        }

        $this->idList        = $list;
        $this->countByForums = $count;

        return $this;
    }

    /**
     * Размер очереди премодерации
     */
    protected function getcount(): int
    {
        return \count($this->idList);
    }

    /**
     * Количество страниц в очереди
     */
    public function numPages(): int
    {
        return (int) \ceil($this->count / $this->c->user->disp_posts);
    }

    /**
     * Возвращает список черновиков со старницы $page для премодерации
     */
    public function view(int $page): array
    {
        $offset = ($page - 1) * $this->c->user->disp_posts;
        $ids    = \array_slice($this->idList, $offset, $this->c->user->disp_topics);

        if (empty($ids)) {
            return [];
        }

        $userIds = [];
        $result  = $this->c->drafts->loadByIds($ids);

        foreach ($result as $draft) {
            ++$offset;

            if ($draft instanceof Draft) {
                $draft->__postNumber = $offset;

                if ($draft->poster_id > 0) {

                    $userIds[$draft->poster_id] = $draft->poster_id;
                } else {
                    $draft->user; // создание гостя до передачи данных в шаблон
                }
            }
        }

        if (! empty($userIds)) {
            $this->c->users->loadByIds($userIds);
        }

        return $result;
    }

    /**
     * Возвращает размер очереди премодерации на основе кэша
     * Создает кэш
     */
    protected function getqueueSize(): int
    {
        if (\is_array($this->countByForums)) {
            $count = $this->countByForums;

        } elseif (! \is_array($count = $this->c->Cache->get(self::CACHE_KEY))) {
            $this->init();

            $count = $this->countByForums;

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $count)) {
                throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
            }
        }

        if ($this->c->user->isAdmin) {
            return \array_sum($count);

        } else {
            $fids = $this->fidsForMod ?? $this->c->forums->fidsForMod($this->c->user->id);
            $sum  = 0;

            foreach ($count as $key => $value) {
                if (isset($fids[$key])) {
                    $sum += $value;
                }
            }

            return $sum;
        }
    }

    /**
     * Сбрасывает кеш
     */
    public function reset(): Premod
    {
        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to remove key from cache - ' . self::CACHE_KEY);
        }

        return $this;
    }
}
