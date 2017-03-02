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
            'title' => __('Forum rules'),
            'rules' => $this->config['o_rules_message'],
            'formAction' => null,
        ];
        return $this;
    }

    /**
     * Подготавливает данные для шаблона
     * @return Page
     */
    public function confirmation()
    {
        $this->index = 'register';
        $this->c->Lang->load('register');

        $this->titles = [
            __('Forum rules'),
        ];
        $this->data = [
            'title' => __('Forum rules'),
            'rules' => $this->config['o_rules'] == '1' ?
                $this->config['o_rules_message']
                : __('If no rules'),
            'formAction' => $this->c->Router->link('RegisterForm'),
            'formToken' => $this->c->Csrf->create('RegisterForm'),
            'formHash' => $this->c->Csrf->create('Register'),
        ];
        return $this;
    }
}
