<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Rules extends Page
{
    /**
     * Подготавливает данные для шаблона
     *
     * @return Page
     */
    public function view()
    {
        $this->fIndex     = 'rules';
        $this->nameTpl    = 'rules';
        $this->onlinePos  = 'rules';
        $this->canonical  = $this->c->Router->link('Rules');
        $this->title      = \ForkBB\__('Forum rules');
        $this->crumbs     = $this->crumbs([$this->c->Router->link('Rules'), \ForkBB\__('Forum rules')]);
        $this->rules      = $this->c->config->o_rules_message;

        return $this;
    }

    /**
     * Подготавливает данные для шаблона
     *
     * @return Page
     */
    public function confirmation()
    {
        $this->c->Lang->load('register');

        $this->fIndex     = 'register';
        $this->nameTpl    = 'rules';
        $this->onlinePos  = 'rules';
        $this->robots     = 'noindex';
        $this->title      = \ForkBB\__('Forum rules');
        $this->crumbs     = $this->crumbs(\ForkBB\__('Forum rules'), [$this->c->Router->link('Register'), \ForkBB\__('Register')]);
        $this->rules      = $this->c->config->o_rules == '1' ? $this->c->config->o_rules_message : \ForkBB\__('If no rules');
        $this->form       = $this->formAgree();

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @return array
     */
    protected function formAgree()
    {
        return [
            'action' => $this->c->Router->link('RegisterForm'),
            'hidden' => [
                'token' => $this->c->Csrf->create('RegisterForm'),
            ],
            'sets'   => [
                'agree' => [
                    'fields' => [
                        'agree' => [
                            'type'    => 'checkbox',
                            'label'   => \ForkBB\__('Agree'),
                            'value'   => $this->c->Csrf->create('Register'),
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'register' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Register'),
                    'accesskey' => 's',
                ],
            ],
        ];
    }
}
