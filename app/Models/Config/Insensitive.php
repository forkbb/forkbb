<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;

class Insensitive extends Method
{
    const CACHE_KEY = 'case_insensitive';

    /**
     * Проверяет регистронезависимое сравнение в БД через таблицу config
     */
    public function insensitive(): bool
    {
        $result = $this->c->Cache->get(self::CACHE_KEY, null);

        if (! \is_bool($result)) {
            $like  = 'pgsql' === $this->c->DB->getType() ? 'ILIKE' : 'LIKE';
            $query = "SELECT conf_value
                FROM ::config
                WHERE conf_name {$like} 's#_регистр' ESCAPE '#'";

            $result = 'Ok' === $this->c->DB->query($query)->fetchColumn();

            if (true !== $this->c->Cache->set(self::CACHE_KEY, $result)) {
                throw new RuntimeException('Unable to write value to cache - ' . self::CACHE_KEY);
            }
        }

        return $result;
    }
}
