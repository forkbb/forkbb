<?php

declare(strict_types=1);

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Method;
use ForkBB\Models\SmileyList\Model as SmileyList;
use PDO;
use RuntimeException;

class Load extends Method
{
    /**
     * Заполняет модель данными из БД
     * Создает кеш
     */
    public function load(): SmileyList
    {
        $query = 'SELECT id, sm_code, sm_image, sm_position
            FROM ::smilies
            ORDER BY sm_position';

        $list = $this->c->DB->query($query)->fetchAll(PDO::FETCH_UNIQUE);

        $this->model->list = $list;

        if (true !== $this->c->Cache->set('smilies', $list)) {
            throw new RuntimeException('Unable to write value to cache - smilies');
        }

        return $this->model;
    }
}
