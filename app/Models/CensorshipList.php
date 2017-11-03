<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;

class CensorshipList extends Model
{
    /**
     * Загружает список цензуры из кеша/БД
     * 
     * @return BanList
     */
    public function init()
    {
        if ($this->c->Cache->has('censorship')) {
            $list = $this->c->Cache->get('censorship');
            $this->searchList   = $list['searchList'];
            $this->replaceList  = $list['replaceList'];
        } else {
            $this->load();
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
        if ($this->c->config->o_censoring == '1') {
            return (string) preg_replace($this->searchList, $this->replaceList,  $str);
        } else {
            return $str;
        }
    }
}
