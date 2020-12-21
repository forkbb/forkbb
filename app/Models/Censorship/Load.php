<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Method;
use PDO;

class Load extends Method
{
    /**
     * Загружает весь список нецензурных слов
     */
    public function load(): array
    {
        $query = 'SELECT ce.id, ce.search_for, ce.replace_with
            FROM ::censoring AS ce
            ORDER BY REPLACE(ce.search_for, \'*\', \'\')';

        return $this->c->DB->query($query)->fetchAll(PDO::FETCH_UNIQUE);
    }
}
