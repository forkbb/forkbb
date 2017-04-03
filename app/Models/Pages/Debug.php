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
            'numQueries' => 0, //$this->c->DB->get_num_queries(),
            'memory' => $this->size(memory_get_usage()),
            'peak' => $this->size(memory_get_peak_usage()),
        ];

        if ($this->c->DEBUG > 1) {
            $this->data['queries'] = $this->c->DB->get_saved_queries();
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
