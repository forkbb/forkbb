<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Provider\Driver;
use function \ForkBB\__;

class Providers extends Admin
{
    /**
     * Выводит сообщение
     */
    protected function mDisabled(): void
    {
        if (
            ! \extension_loaded('curl')
            && ! \filter_var(\ini_get('allow_url_fopen'), \FILTER_VALIDATE_BOOL)
        ) {
            $this->fIswev = [FORK_MESS_ERR, 'cURL disabled'];

        } elseif (1 !== $this->c->config->b_oauth_allow) {
            $this->fIswev = [FORK_MESS_WARN, ['OAuth authorization disabled', $this->c->Router->link('AdminOptions', ['#' => 'id-fs-registration'])]];
        }
    }

    /**
     * Просмотр, редактирвоание и добавление категорий
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_providers');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'           => 'token:AdminProviders',
                    'form.*.pr_pos'   => 'required|integer|min:0|max:9999999999',
                    'form.*.pr_allow' => 'checkbox',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $this->c->providers->update($v->form);

                return $this->c->Redirect->page('AdminProviders')->message('Providers updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->mDisabled();

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'options';
        $this->aCrumbs[] = [$this->c->Router->link('AdminProviders'), 'Providers'];
        $this->form      = $this->formView();
        $this->classForm = ['providers', 'inline'];
        $this->titleForm = 'Providers';

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formView(): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminProviders'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminProviders'),
            ],
            'sets'   => [],
            'btns'   => [
                'save' => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        foreach ($this->c->providers->init()->repository as $provider) {
            $fields = [];
            $fields["name-{$provider->name}"] = [
                'class'   => ['name', 'provider'],
                'type'    => 'btn',
                'value'   => __($provider->name),
                'caption' => 'Provider label',
                'href'    => $this->c->Router->link('AdminProvider', ['name' => $provider->name]),
            ];
            $fields["form[{$provider->name}][pr_pos]"] = [
                'class'   => ['position', 'provider'],
                'type'    => 'number',
                'min'     => '0',
                'max'     => '9999999999',
                'value'   => $provider->pos,
                'caption' => 'Position label',
            ];
            $fields["form[{$provider->name}][pr_allow]"] = [
                'class'   => ['allow', 'provider'],
                'type'    => 'checkbox',
                'checked' => $provider->allow,
                'caption' => 'Allow label',
            ];
            $form['sets']["provider-{$provider->name}"] = [
                'class'  => ['provider', 'inline'],
                'legend' => $provider->name,
                'fields' => $fields,
            ];
        }

        return $form;
    }

    /**
     * Просмотр, редактирвоание и добавление категорий
     */
    public function edit(array $args, string $method): Page
    {
        $provider = $this->c->providers->init()->get($args['name']);

        if (! $provider instanceof Driver) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_providers');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'         => 'token:AdminProvider',
                    'client_id'     => 'exist|string:trim|max:255',
                    'client_secret' => 'exist|string:trim|max:255',
                    'changeData'    => 'checkbox',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                if ($v->changeData) {
                    $this->c->providers->update([
                        $provider->name => [
                            'pr_cl_id'  => $v->client_id,
                            'pr_cl_sec' => $v->client_secret,
                         ],
                    ]);

                    $message = 'Provider updated redirect';
                    $status  = FORK_MESS_SUCC;

                } else {
                    $message = 'No confirm redirect';
                    $status  = FORK_MESS_WARN;
                }

                return $this->c->Redirect->page('AdminProvider', $args)->message($message, $status);
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->mDisabled();

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'options';
        $this->aCrumbs[] = [$this->c->Router->link('AdminProvider', ['name' => $provider->name]), $provider->name];
        $this->aCrumbs[] = [$this->c->Router->link('AdminProviders'), 'Providers'];
        $this->form      = $this->formEdit($provider);
        $this->classForm = ['provider'];
        $this->titleForm = $provider->name;

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formEdit(Driver $provider): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminProvider', ['name' => $provider->name]),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminProvider', ['name' => $provider->name]),
            ],
            'sets'   => [
                'provider' => [
                    'fields' => [
                        'callback'      => [
                            'type'      => 'str',
                            'value'     => $provider->linkCallback,
                            'caption'   => 'Callback label',
                            'help'      => 'Callback help',
                        ],
                        'client_id'     => [
                            'type'      => 'text',
                            'maxlength' => '255',
                            'value'     => $provider->client_id,
                            'caption'   => 'ID label',
                            'help'      => 'ID help',
                        ],
                        'client_secret' => [
                            'type'      => 'text',
                            'maxlength' => '255',
                            'value'     => '' == $provider->client_secret ? '' : '********',
                            'caption'   => 'Secret label',
                            'help'      => 'Secret help',
                        ],
                        'changeData'    => [
                            'type'      => 'checkbox',
                            'caption'   => '',
                            'label'     => 'Change data help',
                        ],
                    ],
                ]
            ],
            'btns'   => [
                'save' => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        return $form;
    }
}
