<?php

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    /**
     * Загружает список цензуры из кеша/БД
     *
     * @return Censorship\Model
     */
    public function init()
    {
        if ('1' == $this->c->config->o_censoring) {
            if ($this->c->Cache->has('censorship')) {
                $list = $this->c->Cache->get('censorship');
                $this->searchList   = $list['searchList'];
                $this->replaceList  = $list['replaceList'];
            } else {
                $this->refresh();
            }
        }
        return $this;
    }

    /**
     * Выполняет цензуру при необходимости
     *
     * @param string $str
     *
     * @return string
     */
    public function censor($str)
    {
        if ('1' == $this->c->config->o_censoring) {
            return (string) \preg_replace($this->searchList, $this->replaceList,  $str);
        } else {
            return $str;
        }
    }
}
