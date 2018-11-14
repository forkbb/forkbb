<?php

namespace ForkBB\Models\SmileyList;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    /**
     * Загружает список смайлов из кеша/БД
     *
     * @return SmileyList\Model
     */
    public function init()
    {
        if ($this->c->Cache->has('smilies')) {
            $this->list = $this->c->Cache->get('smilies');
        } else {
            $this->load();
        }
        return $this;
    }
}
