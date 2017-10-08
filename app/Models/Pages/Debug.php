<?php

namespace ForkBB\Models\Pages;

class Debug extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'layouts/debug';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = null;

    /**
     * Подготавливает данные для шаблона
     * @return Page
     */
    public function debug()
    {
        $this->data = [
            'time' => $this->number(microtime(true) - $this->c->START, 3),
            'numQueries' => $this->c->DB->getCount(),
            'memory' => $this->size(memory_get_usage()),
            'peak' => $this->size(memory_get_peak_usage()),
        ];

        if ($this->c->DEBUG > 1) {
            $total = 0;
            $this->data['queries'] = array_map(
                function($a) use (&$total) {
                    $total += $a[1];
                    $a[1] = $this->number($a[1], 3);
                    return $a;
                }, 
                $this->c->DB->getQueries()
            );
            $this->data['total'] = $this->number($total, 3);
        } else {
            $this->data['queries'] = null;
        }

        return $this;
    }

    /**
     * Возвращает HTTP заголовки страницы
     * @return array
     */
    public function httpHeaders()
    {
        return [];
    }

    /**
     * Возвращает данные для шаблона
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
