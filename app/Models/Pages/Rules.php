<?php
/**
 * This file is part of the ForkBB <https://forkbb.org, https://github.com/forkbb>.
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
//        $this->fIndex       = self::FI_RULES;
        $this->nameTpl      = 'rules';
        $this->onlinePos    = 'rules';
        $this->onlineDetail = null;
        $this->canonical    = $this->c->Router->link('Rules');
        $this->crumbs       = $this->crumbs(
            [
                $this->canonical,
                'Forum rules',
            ]
        );
        $this->rules        = $this->config->o_rules_message;

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
                $this->canonical,
                'Register',
            ]
        );
        $this->rules        = 1 === $this->config->b_rules ? $this->config->o_rules_message : __('If no rules');
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
                            'label'   => 1 === $this->config->b_privacy
                                ? ['Agree and privacy %s', $this->c->Router->link('Privacy')]
                                : 'Agree',
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

    /**
     * Подготавливает данные для шаблона
     */
    public function privacyView(): Page
    {
        $this->identifier   = 'privacy';
        $this->nameTpl      = 'privacy';
        $this->onlinePos    = 'privacy';
        $this->onlineDetail = null;
        $this->canonical    = $this->c->Router->link('Privacy');
        $this->crumbs       = $this->crumbs(
            [
                $this->canonical,
                'Privacy policy',
            ]
        );
        $this->privacy      = $this->config->o_privacy_message;

        return $this;
    }
}
