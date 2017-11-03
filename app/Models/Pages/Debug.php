<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Debug extends Page
{
    /**
     * Подготавливает данные для шаблона
     * 
     * @return Page
     */
    public function debug()
    {
        if ($this->c->DEBUG > 1) {
            $total = 0;
            $this->queries = array_map(
                function($a) use (&$total) {
                    $total += $a[1];
                    $a[1] = $this->number($a[1], 3);
                    return $a;
                }, 
                $this->c->DB->getQueries()
            );
            $this->total = $this->number($total, 3);
        } else {
            $this->queries = null;
        }

        $this->nameTpl    = 'layouts/debug';
        $this->onlinePos  = null;
        $this->numQueries = $this->c->DB->getCount();
        $this->memory     = $this->size(memory_get_usage());
        $this->peak       = $this->size(memory_get_peak_usage());
        $this->time       = $this->number(microtime(true) - $this->c->START, 3);
        
        return $this;
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare()
    {
    }

    /**
     * Возвращает HTTP заголовки страницы
     * $this->httpHeaders
     * 
     * @return array
     */
    protected function getHttpHeaders()
    {
        return [];
    }
}
