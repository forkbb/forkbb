<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\Pages\RegLogTrait;
use ForkBB\Models\User\User;
use function \ForkBB\__;

class OAuth extends Profile
{
    use RegLogTrait;

    /**
     * Подготавливает данные для шаблона списка аккаунтов
     */
    public function list(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->configureOAuth
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('admin_providers');

        $this->crumbs          = $this->crumbs(
            [
                $this->c->Router->link('EditUserOAuth', $args),
                'OAuth accounts',
            ],
            [
                $this->c->Router->link('EditUserProfile', $args),
                'Editing profile',
            ]
        );
        $this->form            = $this->formList($args);
        $this->formOAuth       = $this->reglogForm();
        $this->actionBtns      = $this->btns('edit');
        $this->profileIdSuffix = '-oauth';

        return $this;
    }

    /**
     * Создает массив данных для формы аккаунтов
     */
    protected function formList(array $args): array
    {
        $data = $this->c->providerUser->loadUserData($this->curUser);

        if (0 === \count($data)) {
            $this->fIswev = [FORK_MESS_INFO, 'No linked accounts'];

            return [];
        }

        $fields = [];

        foreach ($data as $cur) {
            $key          = $cur['name'] . '-' . $cur['userId'];
            $args['key']  = $key;
            $value        = __($cur['name']);
            $title        = $value . " ({$cur['userId']})";
            $fields[$key] = [
                'type'  => 'btn',
                'class' => ['oauth-acc-btn'],
                'value' => $value,
                'title' => $title,
                'link'  => $this->c->Router->link('EditUserOAuthAction', $args),
            ];
        }

        return [
            'action' => null,
            'sets'   => [
                'oauth-accounts' => [
                    'class'  => ['account-links'],
                    'legend' => 'Linked accounts',
                    'fields' => $fields,
                ],
            ],
            'btns'   => null,
        ];
    }

    /**
     * Подготавливает данные для шаблона аккаунта
     */
    public function action(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->configureOAuth
        ) {
            return $this->c->Message->message('Bad request');
        }

        list($name, $userId) = \array_pad(\explode('-', $args['key'], 2), 2, null);

        $data   = $this->c->providerUser->loadUserData($this->curUser);
        $puInfo = null;

        foreach ($data as $cur) {
            if (
                $name === $cur['name']
                && $userId === $cur['userId']
            ) {
                $puInfo = $cur;

                break;
            }
        }

        if (empty($puInfo)) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('admin_providers');
        $this->c->Lang->load('validator');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'     => 'token:EditUserOAuthAction',
                    'confirm'   => 'checkbox',
                    'delete'    => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token'           => $args,
                ])->addMessages([
                ]);

            if (
                $v->validation($_POST)
                && '1' === $v->confirm
            ) {
                if (! empty($v->delete)) {
                    $this->c->providerUser->deleteAccount($this->curUser, $name, $userId);

                    return $this->c->Redirect->page('EditUserOAuth', $args)->message('Account removed');
                }
            }

            return $this->c->Redirect->page('EditUserOAuthAction', $args)->message('No confirm redirect');
        }

        $this->crumbs          = $this->crumbs(
            [
                $this->c->Router->link('EditUserOAuthAction', $args),
                $name,
            ],
            [
                $this->c->Router->link('EditUserOAuth', $args),
                'OAuth accounts',
            ],
            [
                $this->c->Router->link('EditUserProfile', $args),
                'Editing profile',
            ]
        );
        $this->form            = $this->formAction($puInfo, $args);
        $this->actionBtns      = $this->btns('edit');
        $this->profileIdSuffix = '-oauth-a';

        return $this;
    }

    /**
     * Создает массив данных для формы днействия
     */
    protected function formAction(array $info, array $args): array
    {
        return [
            'action' => $this->c->Router->link('EditUserOAuthAction', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserOAuthAction', $args),
            ],
            'sets'   => [
                'oauth-account' => [
                    'class'  => ['data-edit'],
                    'fields' => [
                        'provider' => [
                            'type'    => 'str',
                            'class'   => ['pline'],
                            'caption' => 'Provider label',
                            'value'   => __($info['name']),
                        ],
                        'userId' => [
                            'type'    => 'str',
                            'class'   => ['pline'],
                            'caption' => 'Identifier label',
                            'value'   => $info['userId'],
                        ],
                        'userEmail' => [
                            'type'    => 'str',
                            'class'   => ['pline'],
                            'caption' => 'Email label',
                            'value'   => $info['userEmail'],
                        ],
                        'userEmailVerifed' => [
                            'type'    => 'str',
                            'class'   => ['pline'],
                            'caption' => 'Verified label',
                            'value'   => __($info['userEmailVerifed'] ? 'Yes' : 'No'),
                        ],
                        'confirm'  => [
                            'type'    => 'checkbox',
                            'class'   => ['pline'],
                            'label'   => 'Confirm action',
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete' => [
                    'type'  => 'submit',
                    'value' => __('Delete'),
                ],
                'cancel' => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'link'  => $this->c->Router->link('EditUserOAuth', $args),
                ],
            ],
        ];

    }
}
