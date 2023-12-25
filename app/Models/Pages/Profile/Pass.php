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
                    'password'  => 'required|string:trim|max:100000|check_password',
                    'new_pass'  => 'required|string:trim|min:16|max:100000|password',
                    'submit'    => 'required|string',
                ])->addAliases([
                    'new_pass'  => 'New pass',
                    'password'  => 'Your passphrase',
                ])->addArguments([
                    'token'     => $args,
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $this->curUser->password = \password_hash($v->new_pass, \PASSWORD_DEFAULT);
                $this->c->users->update($this->curUser);

                if ($this->rules->my) {
//                    $auth = $this->c->Auth;
//                    $auth->fIswev = [FORK_MESS_SUCC => [__('Pass updated')]];
//                    return $auth->login([], 'GET', $this->curUser->username);
                    return $this->c->Redirect->page('Login')->message('Pass updated', FORK_MESS_SUCC); // ???? нужна передача данных между скриптами не привязанная к пользователю
                } else {
                    return $this->c->Redirect->page('EditUserProfile', $args)->message('Pass updated redirect', FORK_MESS_SUCC);
                }
            }

            $this->fIswev = $v->getErrors();
        }

        $this->identifier      = ['profile', 'profile-pass'];
        $this->crumbs          = $this->crumbs(
            [
                $this->c->Router->link('EditUserPass', $args),
                'Change pass',
            ],
            [
                $this->c->Router->link('EditUserProfile', $args),
                'Editing profile',
            ]
        );
        $this->form            = $this->form($args);
        $this->actionBtns      = $this->btns('edit');
        $this->profileIdSuffix = '-pass';

        return $this;
    }

    /**
     * Создает массив данных для формы
     */
    protected function form(array $args): array
    {
        $form = [
            'action' => $this->c->Router->link('EditUserPass', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('EditUserPass', $args),
            ],
            'sets'   => [
                'new-pass' => [
                    'class'  => ['data-edit'],
                    'fields' => [
                        'new_pass' => [
                            'autofocus' => true,
                            'type'      => 'password',
                            'caption'   => 'New pass',
                            'required'  => true,
                            'minlength' => '16',
                            'pattern'   => '^.*[^ ] [^ ].*$',
                            'help'      => 'Passphrase help',
                        ],
                        'password' => [
                            'type'      => 'password',
                            'caption'   => 'Your passphrase',
                            'required'  => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('Submit'),
                ],
            ],
        ];

        return $form;
    }
}
