<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Stats;

use ForkBB\Models\Model;
use PDO;
use RuntimeException;

class Stats extends Model
{
    const CACHE_KEY = 'stats';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Stats';

    /**
     * Загружает статистику из кеша/БД
     */
    public function init(): Stats
    {
        $list = $this->c->Cache->get(self::CACHE_KEY);

        if (! \is_array($list)) {
            $list = $this->c->users->stats();

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $list)) {
                throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
            }
        }

        $this->userTotal = $list['total'];
        $this->userLast  = $list['last'];

        $query = 'SELECT SUM(f.num_topics), SUM(f.num_posts)
            FROM ::forums AS f';

        list($this->topicTotal, $this->postTotal) = $this->c->DB->query($query)->fetch(PDO::FETCH_NUM);

        return $this;
    }

    /**
     * Сбрасывает кеш статистики
     */
    public function reset(): Stats
    {
        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to remove key from cache - ' . self::CACHE_KEY);
        }

        return $this;
    }
}
