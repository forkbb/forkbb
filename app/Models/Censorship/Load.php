<?php

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
