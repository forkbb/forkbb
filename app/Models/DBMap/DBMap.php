<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'DBMap';

    /**
     * Загружает карту БД из кеша/БД
     */
    public function init(): DBMap
    {
        $map = $this->c->Cache->get('db_map');

        if (! \is_array($map)) {
            $map = $this->c->DB->getMap();

            if (true !== $this->c->Cache->set('db_map', $map)) {
                throw new RuntimeException('Unable to write value to cache - db_map');
            }
        }

        $this->setAttrs($map);

        return $this;
    }

    /**
     * Сбрасывает кеш карты БД
     */
    public function reset(): DBMap
    {
        if (true !== $this->c->Cache->delete('db_map')) {
            throw new RuntimeException('Unable to remove key from cache - db_map');
        }

        return $this;
    }
}
