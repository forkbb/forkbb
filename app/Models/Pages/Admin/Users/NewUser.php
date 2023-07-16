<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Users;
use RuntimeException;
use function \ForkBB\__;

class NewUser extends Users
{
    /**
     * Подготавливает данные для шаблона добавление пользователя
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('register');

        $data = [];

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'    => 'token:AdminUsersNew',
                    'email'    => 'required|string:trim|email:noban,unique',
                    'username' => 'required|string:trim|username|noURL:1',
                    'password' => 'required|string|min:16|max:100000|password',
                ])->addAliases([
                    'email'    => 'Email',
                    'username' => 'Username',
                    'password' => 'Passphrase',
                ])->addMessages([
                    'password.password' => 'Pass format',
                    'username.login'    => 'Login format',
                ]);

                if ($v->validation($_POST)) {
                    $user = $this->c->users->create();

                    $user->username        = $v->username;
                    $user->password        = \password_hash($v->password, \PASSWORD_DEFAULT);
                    $user->group_id        = $this->c->config->i_default_user_group;
                    $user->email           = $v->email;
                    $user->email_confirmed = 0;
                    $user->activate_string = '';
                    $user->u_mark_all_read = \time();
                    $user->email_setting   = $this->c->config->i_default_email_setting;
                    $user->timezone        = $this->c->config->o_default_timezone;
                    $user->language        = $this->c->config->o_default_lang;
                    $user->style           = $this->c->config->o_default_style;
                    $user->registered      = \time();
                    $user->registration_ip = '127.0.0.1';
                    $user->signature       = '';

                    $this->c->users->insert($user);

                    return $this->c->Redirect->page('User', ['id' => $user->id, 'name' => $user->username])
                        ->message('New user added redirect', FORK_MESS_SUCC);
                }

                $this->fIswev = $v->getErrors();
                $data         = $v->getData();
        }

        $this->nameTpl   = 'admin/users';
        $this->formNew   = $this->formNew($data);
        $this->aCrumbs[] = [$this->c->Router->link('AdminUsersNew'), 'Add user'];

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formNew(array $data): array
    {
        return [
            'action' => $this->c->Router->link('AdminUsersNew'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUsersNew'),
            ],
            'sets'   => [
                'reg' => [
                    'legend' => 'Add user legend',
                    'fields' => [
                        'username' => [
                            'autofocus' => true,
                            'type'      => 'text',
                            'maxlength' => $this->user->isAdmin ? '190' : $this->c->USERNAME['max'],
                            'value'     => $data['username'] ?? null,
                            'caption'   => 'Username',
                            'help'      => 'Login format',
                            'required'  => true,
                            'pattern'   => $this->c->USERNAME['jsPattern'],
                        ],
                        'email' => [
                            'type'           => 'text',
                            'maxlength'      => (string) $this->c->MAX_EMAIL_LENGTH,
                            'value'          => $data['email'] ?? null,
                            'caption'        => 'Email',
                            'help'           => 'Email help',
                            'required'       => true,
                            'pattern'        => '.+@.+',
                            'autocapitalize' => 'off',
                        ],
                        'password' => [
                            'type'      => 'text',
                            'caption'   => 'Passphrase',
                            'help'      => 'Passphrase help',
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'add' => [
                    'type'  => 'submit',
                    'value' => __('Add'),
                ],
            ],
        ];
    }
}
