<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;

class SmileyList extends Model
{
    /**
     * Загружает список смайлов из кеша/БД
     *
     * @return SmileyList
     */
    public function init()
    {
        if ($this->c->Cache->has('smilies')) {
            $this->list  = $this->c->Cache->get('smilies');
        } else {
            $this->load();
        }
        return $this;
    }
}
