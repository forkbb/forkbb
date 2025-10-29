<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\DBMap;

use ForkBB\Models\Model;
use RuntimeException;

class DBMap extends Model
{
    const CACHE_KEY = 'db_map';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'DBMap';

    /**
     * Загружает карту БД из кеша/БД
     */
    public function init(): DBMap
    {
        $map = $this->c->Cache->get(self::CACHE_KEY);

        if (! \is_array($map)) {
            $map = $this->c->DB->getMap();

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $map)) {
                throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
            }
        }

        $this->setModelAttrs($map);

        return $this;
    }

    /**
     * Сбрасывает кеш карты БД
     */
    public function reset(): DBMap
    {
        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to remove key from cache - ' . self::CACHE_KEY);
        }

        return $this;
    }
}
