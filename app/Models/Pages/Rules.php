<?php

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use function \ForkBB\__;

class Rules extends Page
{
    /**
     * Подготавливает данные для шаблона
     */
    public function view(): Page
    {
        $this->fIndex     = 'rules';
        $this->nameTpl    = 'rules';
        $this->onlinePos  = 'rules';
        $this->canonical  = $this->c->Router->link('Rules');
        $this->title      = __('Forum rules');
        $this->crumbs     = $this->crumbs(
            [
                $this->c->Router->link('Rules'),
                __('Forum rules'),
            ]
        );
        $this->rules      = $this->c->config->o_rules_message;

        return $this;
    }

    /**
     * Подготавливает данные для шаблона
     */
    public function confirmation(): Page
    {
        $this->c->Lang->load('register');

        $this->fIndex     = 'register';
        $this->nameTpl    = 'rules';
        $this->onlinePos  = 'rules';
        $this->robots     = 'noindex';
        $this->title      = __('Forum rules');
        $this->crumbs     = $this->crumbs(
            __('Forum rules'),
            [
                $this->c->Router->link('Register'),
                __('Register'),
            ]
        );
        $this->rules      = '1' == $this->c->config->o_rules ? $this->c->config->o_rules_message : __('If no rules');
        $this->form       = $this->formAgree();

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formAgree(): array
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
                            'label'   => __('Agree'),
                            'value'   => $this->c->Csrf->create('Register'),
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'register' => [
                    'type'      => 'submit',
                    'value'     => __('Register'),
//                    'accesskey' => 's',
                ],
            ],
        ];
    }
}
