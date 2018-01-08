<?php

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Method;
use PDO;

class Load extends Method
{
    /**
     * Загружает весь список нецензурных слов
     *
     * @return array
     */
    public function load()
    {
        $sql = 'SELECT id, search_for, replace_with 
                FROM ::censoring 
                ORDER BY REPLACE(search_for, \'*\', \'\')';
        return $this->c->DB->query($sql)->fetchAll(PDO::FETCH_UNIQUE);
    }
}
