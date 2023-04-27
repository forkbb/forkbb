<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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
        $config = $this->c->Cache->get('config');

        if (! \is_array($config)) {
            $config = $this->load();

            if (true !== $this->c->Cache->set('config', $config)) {
                throw new RuntimeException('Unable to write value to cache - config');
            }
        }

        $this->setAttrs($config);

        return $this;
    }

    /**
     * Сбрасывает кеш конфига
     */
    public function reset(): Config
    {
        if (true !== $this->c->Cache->delete('config')) {
            throw new RuntimeException('Unable to remove key from cache - config');
        }

        return $this;
    }
}
