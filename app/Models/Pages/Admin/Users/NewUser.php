<?php

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Users;
use RuntimeException;

class NewUser extends Users
{
    /**
     * Подготавливает данные для шаблона добавление пользователя
     *
     * @param array $args
     * @param string $method
     *
     * @throws RuntimeException
     *
     * @return Page
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
                    'username' => 'required|string:trim,spaces|username',
                    'password' => 'required|string|min:16|password',
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
                    $user->password        = \password_hash($v->password, PASSWORD_DEFAULT);
                    $user->group_id        = $this->c->config->o_default_user_group;
                    $user->email           = $v->email;
                    $user->email_confirmed = 0;
                    $user->activate_string = '';
                    $user->u_mark_all_read = \time();
                    $user->email_setting   = $this->c->config->o_default_email_setting;
                    $user->timezone        = $this->c->config->o_default_timezone;
                    $user->dst             = $this->c->config->o_default_dst;
                    $user->language        = $this->c->config->o_default_lang;
                    $user->style           = $this->c->config->o_default_style;
                    $user->registered      = \time();
                    $user->registration_ip = '127.0.0.1';
                    $user->signature       = '';

                    $this->c->users->insert($user);

                    return $this->c->Redirect->page('User', ['id' => $user->id, 'name' => $user->username])->message('New user added redirect');
                }

                $this->fIswev = $v->getErrors();
                $data         = $v->getData();
        }

        $this->nameTpl    = 'admin/users';
        $this->formNew    = $this->formNew($data);
        $this->aCrumbs[]  = [$this->c->Router->link('AdminUsersNew'), \ForkBB\__('Add user')];


        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param array $data
     *
     * @return array
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
                    'legend' => \ForkBB\__('Add user legend'),
                    'fields' => [
                        'username' => [
                            'autofocus' => true,
                            'type'      => 'text',
                            'maxlength' => 25,
                            'value'     => $data['username'] ?? null,
                            'caption'   => \ForkBB\__('Username'),
                            'info'      => \ForkBB\__('Login format'),
                            'required'  => true,
                            'pattern'   => '^.{2,25}$',
                        ],
                        'email' => [
                            'type'      => 'text',
                            'maxlength' => 80,
                            'value'     => $data['email'] ?? null,
                            'caption'   => \ForkBB\__('Email'),
                            'info'      => \ForkBB\__('Email info'),
                            'required'  => true,
                            'pattern'   => '.+@.+',
                        ],
                        'password' => [
                            'type'      => 'text',
                            'caption'   => \ForkBB\__('Passphrase'),
                            'info'      => \ForkBB\__('Pass format') . ' ' . \ForkBB\__('Pass info'),
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'add' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Add'),
                    'accesskey' => 's',
                ],
            ],
        ];
    }
}
