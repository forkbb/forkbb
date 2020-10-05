<?php

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Model as ParentModel;
use RuntimeException;

class Model extends ParentModel
{
    /**
     * Загружает список смайлов из кеша/БД
     */
    public function init(): Model
    {
        $this->list = $this->c->Cache->get('smilies');

        if (! \is_array($this->list)) {
            $this->load();
        }

        return $this;
    }

    /**
     * Сбрасывает кеш смайлов
     */
    public function reset(): Model
    {
        if (true !== $this->c->Cache->delete('smilies')) {
            throw new RuntimeException('Unable to remove key from cache - smilies');
        }

        return $this;
    }
}
