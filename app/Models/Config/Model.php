<?php

declare(strict_types=1);

namespace ForkBB\Models\Config;

use ForkBB\Models\DataModel;
use RuntimeException;

class Model extends DataModel
{
    /**
     * Заполняет модель данными из кеша/БД
     * Создает кеш
     */
    public function init(): Model
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
    public function reset(): Model
    {
        if (true !== $this->c->Cache->delete('config')) {
            throw new RuntimeException('Unable to remove key from cache - config');
        }

        return $this;
    }
}
