<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\AdminList;

use ForkBB\Models\Model;
use RuntimeException;

class AdminList extends Model
{
    const CACHE_KEY = 'admins';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'AdminList';

    /**
     * Загружает список id админов из кеша/БД
     * Создает кеш
     */
    public function init(): AdminList
    {
        $this->list = $this->c->Cache->get(self::CACHE_KEY);

        if (! \is_array($this->list)) {
            $this->list = \array_flip($this->c->users->adminsIds());

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $this->list)) {
                throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
            }
        }

        return $this;
    }

    /**
     * Сбрасывает кеш списка id админов
     */
    public function reset(): AdminList
    {
        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to remove key from cache - ' . self::CACHE_KEY);
        }

        return $this;
    }
}
