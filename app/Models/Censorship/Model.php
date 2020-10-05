<?php

namespace ForkBB\Models\Censorship;

use ForkBB\Models\Model as ParentModel;

class Model extends ParentModel
{
    /**
     * Загружает список цензуры из кеша/БД
     */
    public function init(): Model
    {
        if ('1' == $this->c->config->o_censoring) {
            $list = $this->c->Cache->get('censorship');

            if (isset($list['searchList'], $list['replaceList'])) {
                $this->searchList  = $list['searchList'];
                $this->replaceList = $list['replaceList'];
            } else {
                $this->refresh();
            }
        }

        return $this;
    }

    /**
     * Выполняет цензуру при необходимости
     */
    public function censor(string $str): string
    {
        if ('1' == $this->c->config->o_censoring) {
            return (string) \preg_replace($this->searchList, $this->replaceList,  $str);
        } else {
            return $str;
        }
    }
}
