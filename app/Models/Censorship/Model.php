<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Model as ParentModel;
use RuntimeException;

class Model extends ParentModel
{
    /**
     * Загружает список цензуры из кеша/БД
     * Создает кеш
     */
    public function init(): Model
    {
        if ('1' == $this->c->config->o_censoring) {
            $list = $this->c->Cache->get('censorship');

            if (! isset($list['searchList'], $list['replaceList'])) {
                $list = $this->refresh();

                if (true !== $this->c->Cache->set('censorship', $list)) {
                    throw new RuntimeException('Unable to write value to cache - censorship');
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
    public function censor(string $str): string
    {
        if ('1' == $this->c->config->o_censoring) {
            return (string) \preg_replace($this->searchList, $this->replaceList,  $str);
        } else {
            return $str;
        }
    }

    /**
     * Сбрасывает кеш цензуры
     */
    public function reset(): Model
    {
        if (true !== $this->c->Cache->delete('censorship')) {
            throw new RuntimeException('Unable to remove key from cache - censorship');
        }

        return $this;
    }
}
