<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Profile;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Profile;
use ForkBB\Models\User\Model as User;
use function \ForkBB\__;

class Pass extends Profile
{
    /**
     * Подготавливает данные для шаблона смены пароля
     */
    public function pass(array $args, string $method): Page
    {
        if (
            false === $this->initProfile($args['id'])
            || ! $this->rules->editPass
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_password' => [$this, 'vCheckPassword'],
                ])->addRules([
                    'token'     => 'token:EditUserPass',
                    'password'  => 'required|string:trim|check_password',
                    'new_pass'  => 'required|string:trim|password',
                    'submit'    => 'required|string',
                ])->addAliases([
                    'new_pass'  => 'New pass',
                    'password'  => 'Your passphrase',
                ])->addArguments([
                    'token'     => ['id' => $this->curUser->id],
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
//                if (\password_verify($v->new_pass, $this->curUser->password)) {
//                    return $this->c->Redirect->page('EditUserProfile', ['id' => $this->curUser->id])->message('Email is old redirect');
//                }

                $this->curUser->password = \password_hash($v->new_pass, \PASSWORD_DEFAULT);
                $this->c->users->update($this->curUser);

                if ($this->rules->my) {
#                    $auth = $this->c->Auth;
#                    $auth->fIswev = ['s' => [__('Pass updated')]];
#                    return $auth->login([], 'GET', $this->curUser->username);
                    return $this->c->Redirect->page('Login')->message('Pass updated'); // ???? нужна передача данных между скриптами не привязанная к пользователю
                } else {
                    return $this->c->Redirect->page('EditUserProfile', ['id' => $this->curUser->id])->message('Pass updated redirect');
                }
            }

            $this->fIswev = $v->getErrors();
        }

        $this->crumbs     = $this->crumbs(
            [
                $this->c->Router->link(
                    'EditUserPass',
                    [
                        'id' => $this->curUser->id,
                    ]
                ),
                __('Change pass'),
            ],
            [
                $this->c->Router->link(
                    'EditUserProfile',
                    [
                        'id' => $this->curUser->id,
                    ]
                ),
                __('Editing profile'),
            ]
        );
        $this->form       = $this->form();
        $this->actionBtns = $this->btns('edit');

        return $this;
    }

    /**
     * Создает массив данных для формы
     */
    protected function form(): array
    {
        $form = [
            'action' => $this->c->Router->link(
                'EditUserPass',
                [
                    'id' => $this->curUser->id,
                ]
            ),
            'hidden' => [
                'token' => $this->c->Csrf->create(
                    'EditUserPass',
                    [
                        'id' => $this->curUser->id,
                    ]
                ),
            ],
            'sets'   => [
                'new-pass' => [
                    'class'  => 'data-edit',
                    'fields' => [
                        'new_pass' => [
                            'type'      => 'password',
                            'caption'   => __('New pass'),
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                            'info'      => __('Pass format') . ' ' . __('Pass info'),
                        ],
                        'password' => [
                            'type'      => 'password',
                            'caption'   => __('Your passphrase'),
                            'required'  => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => __('Submit'),
//                    'accesskey' => 's',
                ],
            ],
        ];

        return $form;
    }
}
