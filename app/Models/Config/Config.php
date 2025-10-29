<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Config;

use ForkBB\Models\DataModel;
use RuntimeException;

class Config extends DataModel
{
    const CACHE_KEY = 'config';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Config';

    /**
     * Заполняет модель данными из кеша/БД
     * Создает кеш
     */
    public function init(): Config
    {
        $config = $this->c->Cache->get(self::CACHE_KEY);

        if (! \is_array($config)) {
            $config = $this->load();

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $config)) {
                throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
            }
        }

        $this->setModelAttrs($config);

        return $this;
    }

    /**
     * Сбрасывает кеш конфига
     */
    public function reset(): Config
    {
        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to remove key from cache - ' . self::CACHE_KEY);
        }

        return $this;
    }
}
