<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

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
        $this->identifier   = 'rules';
        $this->fIndex       = self::FI_RULES;
        $this->nameTpl      = 'rules';
        $this->onlinePos    = 'rules';
        $this->onlineDetail = null;
        $this->canonical    = $this->c->Router->link('Rules');
        $this->crumbs       = $this->crumbs(
            [
                $this->c->Router->link('Rules'),
                'Forum rules',
            ]
        );
        $this->rules        = $this->c->config->o_rules_message;

        return $this;
    }

    /**
     * Подготавливает данные для шаблона
     */
    public function confirmation(): Page
    {
        $this->c->Lang->load('register');

        $this->identifier   = 'rules-reg';
        $this->fIndex       = self::FI_REG;
        $this->nameTpl      = 'rules';
        $this->onlinePos    = 'rules';
        $this->onlineDetail = null;
        $this->canonical    = $this->c->Router->link('Register');
        $this->robots       = 'noindex';
        $this->crumbs       = $this->crumbs(
            'Forum rules',
            [
                $this->c->Router->link('Register'),
                'Register',
            ]
        );
        $this->rules        = 1 === $this->c->config->b_rules ? $this->c->config->o_rules_message : __('If no rules');
        $this->form         = $this->formAgree();

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formAgree(): array
    {
        return [
            'action'  => $this->c->Router->link('RegisterForm'),
            'enctype' => 'multipart/form-data',
            'hidden'  => [
                'token' => $this->c->Csrf->create('RegisterForm'),
            ],
            'sets'    => [
                'agree' => [
                    'fields' => [
                        'agree' => [
                            'type'    => 'checkbox',
                            'label'   => 'Agree',
                            'value'   => $this->c->Csrf->create('Register'),
                        ],
                    ],
                ],
            ],
            'btns'    => [
                'register' => [
                    'type'  => 'submit',
                    'value' => __('Register'),
                ],
            ],
        ];
    }
}
