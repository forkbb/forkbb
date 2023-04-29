<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Model;
use RuntimeException;

class SmileyList extends Model
{
    const CACHE_KEY = 'smilies';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'SmileyList';

    /**
     * Загружает список смайлов из кеша/БД
     * Создает кеш
     */
    public function init(): SmileyList
    {
        $list = $this->c->Cache->get(self::CACHE_KEY);

        if (! \is_array($list)) {
            $list = $this->load();

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $list)) {
                throw new RuntimeException('Unable to write value to cache - smilies');
            }
        }

        $this->list = $list;

        return $this;
    }

    /**
     * Сбрасывает кеш смайлов
     */
    public function reset(): SmileyList
    {
        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to remove key from cache - smilies');
        }

        return $this;
    }
}
