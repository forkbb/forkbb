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
     * Подготавливает данные для шаблона
     * @return Page
     */
    public function debug()
    {
        $this->data = [
            'time' => $this->number(microtime(true) - (empty($_SERVER['REQUEST_TIME_FLOAT']) ? $this->c->getParameter('START') : $_SERVER['REQUEST_TIME_FLOAT']), 3),
            'numQueries' => $this->c->get('DB')->get_num_queries(),
            'memory' => $this->size(memory_get_usage()),
            'peak' => $this->size(memory_get_peak_usage()),
        ];

        if (defined('PUN_SHOW_QUERIES')) {
            $this->data['queries'] = $this->c->get('DB')->get_saved_queries();
        } else {
            $this->data['queries'] = null;
        }

        return $this;
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации
     * @return array
     */
    protected function fNavigation()
    {
        return [];
    }

    /**
     * Возвращает HTTP заголовки страницы
     * @return array
     */
    public function getHeaders()
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
