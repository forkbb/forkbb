<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Config;

use ForkBB\Models\Method;

class Insensitive extends Method
{
    /**
     * Проверяет регистронезависимое сравнение в БД через таблицу config
     */
    public function insensitive(): bool
    {
        $result = $this->c->Cache->get('case_insensitive', null);

        if (! \is_bool($result)) {
            $like  = 'pgsql' === $this->c->DB->getType() ? 'ILIKE' : 'LIKE';
            $query = "SELECT conf_value
                FROM ::config
                WHERE conf_name {$like} 's#_регистр' ESCAPE '#'";

            $result = 'Ok' === $this->c->DB->query($query)->fetchColumn();

            if (true !== $this->c->Cache->set('case_insensitive', $result)) {
                throw new RuntimeException('Unable to write value to cache - case_insensitive');
            }
        }

        return $result;
    }
}
