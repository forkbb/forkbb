<?php

declare(strict_types=1);

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Method;
use PDO;

class Load extends Method
{
    /**
     * Загружает данные из БД для модели и кеша
     */
    public function load(): array
    {
        $query = 'SELECT id, sm_code, sm_image, sm_position
            FROM ::smilies
            ORDER BY sm_position';

        return $this->c->DB->query($query)->fetchAll(PDO::FETCH_UNIQUE);
    }
}
