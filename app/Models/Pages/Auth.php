<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;

class Auth extends Page
{
    /**
     * Выход пользователя
     *
     * @param array $args
     *
     * @return Page
     */
    public function logout($args)
    {
        if (! $this->c->Csrf->verify($args['token'], 'Logout', $args)) {
            return $this->c->Redirect->page('Index')->message('Bad token');
        }

        $this->c->Cookie->deleteUser();
        $this->c->Online->delete($this->user);
        $this->c->users->updateLastVisit($this->user);

        $this->c->Lang->load('auth');
        return $this->c->Redirect->page('Index')->message('Logout redirect');
    }

    /**
     * Вход на форум
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function login(array $args, $method)
    {
        $this->c->Lang->load('auth');

        $v = null;
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'login_process' => [$this, 'vLoginProcess'],
                ])->addRules([
                    'token'    => 'token:Login',
                    'redirect' => 'required|referer:Index',
                    'username' => 'required|string',
                    'password' => 'required|string|login_process',
                    'save'     => 'checkbox',
                ])->addAliases([
                    'username' => 'Username',
                    'password' => 'Passphrase',
                ]);

            if ($v->validation($_POST)) {
                return $this->c->Redirect->url($v->redirect)->message('Login redirect');
            }

            $this->fIswev = $v->getErrors();
        }

        $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        $this->fIndex     = 'login';
        $this->nameTpl    = 'login';
        $this->onlinePos  = 'login';
        $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Login');
        $this->regLink    = $this->c->config->o_regs_allow == '1' ? $this->c->Router->link('Register') : null;

        $username         = $v ? $v->username : (isset($args['_username']) ? $args['_username'] : '');
        $save             = $v ? $v->save : 1;
        $redirect         = $v ? $v->redirect : $this->c->Router->validate($ref, 'Index');
        $this->form       = $this->formLogin($username, $save, $redirect);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param string $username
     * @param mixed $save
     * @param string $redirect
     *
     * @return array
     */
    protected function formLogin($username, $save, $redirect)
    {
        return [
            'action' => $this->c->Router->link('Login'),
            'hidden' => [
                'token'    => $this->c->Csrf->create('Login'),
                'redirect' => $redirect,
            ],
            'sets'   => [
                'login' => [
                    'fields' => [
                        'username' => [
                            'autofocus' => true,
                            'type'      => 'text',
                            'value'     => $username,
                            'caption'   => \ForkBB\__('Username'),
                            'required'  => true,
                        ],
                        'password' => [
                            'id'        => 'passinlogin',
                            'autofocus' => true,
                            'type'      => 'password',
                            'caption'   => \ForkBB\__('Passphrase'),
                            'info'      => \ForkBB\__('<a href="%s">Forgotten?</a>', $this->c->Router->link('Forget')),
                            'required'  => true,
                        ],
                        'save' => [
                            'type'    => 'checkbox',
                            'label'   => \ForkBB\__('Remember me'),
                            'value'   => '1',
                            'checked' => $save,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'login' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Sign in'),
                    'accesskey' => 's',
                ],
            ],
        ];
    }

    /**
     * Проверка по базе и вход
     *
     * @param Validator $v
     * @param string $password
     *
     * @return string
     */
    public function vLoginProcess(Validator $v, $password)
    {
        if (! empty($v->getErrors())) {
        } elseif (! ($user = $this->c->users->load($this->c->users->create(['username' => $v->username]))) instanceof User
            || $user->isGuest
        ) {
            $v->addError('Wrong user/pass');
        } elseif ($user->isUnverified) {
            $v->addError('Account is not activated', 'w');
        } else {
            // ошибка в пароле
            if (! \password_verify($password, $user->password)) {
                $v->addError('Wrong user/pass');
            } else {
                // перезаписываем ip админа и модератора - Visman
                if ($user->isAdmMod
                    && $this->c->config->o_check_ip
                    && $user->registration_ip != $this->user->ip
                ) {
                    $user->registration_ip = $this->user->ip;
                }
                // сбросить запрос на смену кодовой фразы
                if (32 === \strlen($user->activate_string)) {
                    $user->activate_string = '';
                }
                // изменения юзера в базе
                $this->c->users->update($user);

                $this->c->Online->delete($this->user);
                $this->c->Cookie->setUser($user, (bool) $v->save);
            }
        }

        return $password;
    }

    /**
     * Запрос на смену кодовой фразы
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function forget(array $args, $method)
    {
        $this->c->Lang->load('auth');

        $v = null;

        if ('POST' === $method) {
            $tmpUser = $this->c->users->create();

            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token' => 'token:Forget',
                    'email' => 'required|string:trim,lower|email:noban,exists,flood',
                ])->addAliases([
                ])->addMessages([
                    'email.email' => 'Invalid email',
                ])->addArguments([
                    'email.email' => $tmpUser, // сюда идет возрат данных по найденному пользователю
                ]);

            if ($v->validation($_POST)) {
                $key  = $this->c->Secury->randomPass(32);
                $hash = $this->c->Secury->hash($tmpUser->email . $key);
                $link = $this->c->Router->link('ChangePassword', ['email' => $tmpUser->email, 'key' => $key, 'hash' => $hash]);
                $tplData = [
                    'fRootLink' => $this->c->Router->link('Index'),
                    'fMailer'   => \ForkBB\__('Mailer', $this->c->config->o_board_title),
                    'username'  => $tmpUser->username,
                    'link'      => $link,
                ];

                try {
                    $isSent = $this->c->Mail
                        ->reset()
                        ->setFolder($this->c->DIR_LANG)
                        ->setLanguage($tmpUser->language)
                        ->setTo($tmpUser->email, $tmpUser->username)
                        ->setFrom($this->c->config->o_webmaster_email, \ForkBB\__('Mailer', $this->c->config->o_board_title))
                        ->setTpl('passphrase_reset.tpl', $tplData)
                        ->send();
                } catch (MailException $e) {
                    $isSent = false;
                }

                if ($isSent) {
                    $tmpUser->activate_string = $key;
                    $tmpUser->last_email_sent = \time();
                    $this->c->users->update($tmpUser);
                    return $this->c->Message->message(\ForkBB\__('Forget mail', $this->c->config->o_admin_email), false, 200);
                } else {
                    return $this->c->Message->message(\ForkBB\__('Error mail', $this->c->config->o_admin_email), true, 200);
                }
            }

            $this->fIswev = $v->getErrors();
        }

        $this->fIndex     = 'login';
        $this->nameTpl    = 'passphrase_reset';
        $this->onlinePos  = 'passphrase_reset';
        $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Passphrase reset');

        $email            = $v ? $v->email : (isset($args['_email']) ? $args['_email'] : '');
        $this->form       = $this->formForget($email);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param string $email
     *
     * @return array
     */
    protected function formForget($email)
    {
        return [
            'action' => $this->c->Router->link('Forget'),
            'hidden' => [
                'token' => $this->c->Csrf->create('Forget'),
            ],
            'sets'   => [
                'forget' => [
                    'fields' => [
                        'email' => [
                            'autofocus' => true,
                            'type'      => 'text',
                            'maxlength' => 80,
                            'value'     => $email,
                            'caption'   => \ForkBB\__('Email'),
                            'info'      => \ForkBB\__('Passphrase reset info'),
                            'required'  => true,
                            'pattern'   => '.+@.+',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Send email'),
                    'accesskey' => 's',
                ],
            ],
        ];
    }

    /**
     * Смена кодовой фразы
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function changePass(array $args, $method)
    {
        if (! \hash_equals($args['hash'], $this->c->Secury->hash($args['email'] . $args['key']))
            || ! ($user = $this->c->users->load($this->c->users->create(['email' => $args['email']]))) instanceof User
            || $user->isGuest
            || empty($user->activate_string)
            || ! \hash_equals($user->activate_string, $args['key'])
        ) {
            // что-то пошло не так
            return $this->c->Message->message('Bad request', false);
        }

        $this->c->Lang->load('auth');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:ChangePassword',
                    'password'  => 'required|string|min:16|password',
                    'password2' => 'required|same:password',
                ])->addAliases([
                    'password'  => 'New pass',
                    'password2' => 'Confirm new pass',
                ])->addArguments([
                    'token' => $args,
                ])->addMessages([
                    'password.password'  => 'Pass format',
                    'password2.same'     => 'Pass not match',
                ]);

            if ($v->validation($_POST)) {
                $user->password        = \password_hash($v->password, \PASSWORD_DEFAULT);
                $user->email_confirmed = 1;
                $user->activate_string = '';

                $this->c->users->update($user);

                $this->fIswev = ['s', \ForkBB\__('Pass updated')];
                return $this->login([], 'GET');
            }

            $this->fIswev = $v->getErrors();
        }
        // активация аккаунта (письмо активации не дошло, заказали восстановление)
        if ($user->isUnverified) {
            $user->group_id        = $this->c->config->o_default_user_group;
            $user->email_confirmed = 1;

            $this->c->users->update($user);

            $this->fIswev = ['i', \ForkBB\__('Account activated')];
        }

        $this->fIndex     = 'login';
        $this->nameTpl    = 'change_passphrase';
        $this->onlinePos  = 'change_passphrase';
        $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Passphrase reset');
        $this->form       = $this->formChange($args);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param array $args
     *
     * @return array
     */
    protected function formChange(array $args)
    {
        return [
            'action' => $this->c->Router->link('ChangePassword', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('ChangePassword', $args),
            ],
            'sets'   => [
                'forget' => [
                    'fields' => [
                        'password' => [
                            'autofocus' => true,
                            'type'      => 'password',
                            'caption'   => \ForkBB\__('New pass'),
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                        'password2' => [
                            'autofocus' => true,
                            'type'      => 'password',
                            'caption'   => \ForkBB\__('Confirm new pass'),
                            'info'      => \ForkBB\__('Pass format') . ' ' . \ForkBB\__('Pass info'),
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'login' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Change passphrase'),
                    'accesskey' => 's',
                ],
            ],
        ];
    }
}
