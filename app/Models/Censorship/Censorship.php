<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Model;
use RuntimeException;

class Censorship extends Model
{
    const CACHE_KEY = 'censorship';

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Censorship';

    /**
     * Загружает список цензуры из кеша/БД
     * Создает кеш
     */
    public function init(): Censorship
    {
        if (1 === $this->c->config->b_censoring) {
            $list = $this->c->Cache->get(self::CACHE_KEY);

            if (! isset($list['searchList'], $list['replaceList'])) {
                $list = $this->refresh();

                if (true !== $this->c->Cache->set(self::CACHE_KEY, $list)) {
                    throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
                }
            }

            $this->searchList  = $list['searchList'];
            $this->replaceList = $list['replaceList'];
        }

        return $this;
    }

    /**
     * Выполняет цензуру при необходимости
     */
    public function censor(string $str, int &$count = 0): string
    {
        if (1 === $this->c->config->b_censoring) {
            $str    = (string) \preg_replace($this->searchList, $this->replaceList,  $str, -1, $cnt);
            $count += $cnt;
        }

        return $str;
    }

    /**
     * Сбрасывает кеш цензуры
     */
    public function reset(): Censorship
    {
        if (true !== $this->c->Cache->delete(self::CACHE_KEY)) {
            throw new RuntimeException('Unable to remove key from cache - ' . self::CACHE_KEY);
        }

        return $this;
    }
}
