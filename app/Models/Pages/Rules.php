<?php

namespace ForkBB\Models\Pages;

class Rules extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'rules';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = 'rules';

    /**
     * Указатель на активный пункт навигации
     * @var string
     */
    protected $index = 'rules';

    /**
     * Подготавливает данные для шаблона
     * @return Page
     */
    public function view()
    {
        $this->titles = [
            __('Forum rules'),
        ];
        $this->data = [
            'Rules' => $this->config['o_rules_message'],
        ];
        return $this;
    }
}
