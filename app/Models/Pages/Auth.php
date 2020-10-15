<?php

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;
use function \ForkBB\__;

class Auth extends Page
{
    /**
     * Выход пользователя
     */
    public function logout(array $args): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'Logout', $args)) {
            return $this->c->Redirect->page('Index')->message($this->c->Csrf->getError());
        }

        $this->c->Cookie->deleteUser();
        $this->c->Online->delete($this->user);
        $this->c->users->updateLastVisit($this->user);

        $this->c->Lang->load('auth');

        return $this->c->Redirect->page('Index')->message('Logout redirect');
    }

    /**
     * Вход на форум
     */
    public function login(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
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
                    'login'    => 'required|string',
                ])->addAliases([
                    'username' => 'Username',
                    'password' => 'Passphrase',
                ]);

            $v = $this->c->Test->beforeValidation($v);

            if ($v->validation($_POST, true)) {
                return $this->c->Redirect->url($v->redirect)->message('Login redirect');
            }

            $this->fIswev = $v->getErrors();
        }

        $ref = $_SERVER['HTTP_REFERER'] ?? '';

        $this->fIndex     = 'login';
        $this->nameTpl    = 'login';
        $this->onlinePos  = 'login';
        $this->robots     = 'noindex';
        $this->titles     = __('Login');
        $this->regLink    = '1' == $this->c->config->o_regs_allow ? $this->c->Router->link('Register') : null;

        $username         = $v ? $v->username : ($args['_username'] ?? '');
        $save             = $v ? $v->save : 1;
        $redirect         = $v ? $v->redirect : $this->c->Router->validate($ref, 'Index');
        $this->form       = $this->formLogin($username, $save, $redirect);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formLogin(string $username, /* mixed */ $save, string $redirect): array
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
                            'caption'   => __('Username'),
                            'required'  => true,
                        ],
                        'password' => [
                            'id'        => 'passinlogin',
                            'autofocus' => true,
                            'type'      => 'password',
                            'caption'   => __('Passphrase'),
                            'info'      => __('<a href="%s">Forgotten?</a>', $this->c->Router->link('Forget')),
                            'required'  => true,
                        ],
                        'save' => [
                            'type'    => 'checkbox',
                            'label'   => __('Remember me'),
                            'value'   => '1',
                            'checked' => $save,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'login' => [
                    'type'      => 'submit',
                    'value'     => __('Sign in'),
//                    'accesskey' => 's',
                ],
            ],
        ];
    }

    /**
     * Проверка по базе и вход
     */
    public function vLoginProcess(Validator $v, $password)
    {
        if (! empty($v->getErrors())) {
        } elseif (
            ! ($user = $this->c->users->loadByName($v->username)) instanceof User
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
                $this->c->users->updateLoginIpCache($user, true); // ????

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
     */
    public function forget(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('auth');

        $v = null;

        if ('POST' === $method) {
            $tmpUser = $this->c->users->create();

            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'  => 'token:Forget',
                    'email'  => 'required|string:trim|email:noban,exists,flood',
                    'submit' => 'required|string',
                ])->addAliases([
                ])->addMessages([
                    'email.email' => 'Invalid email',
                ])->addArguments([
                    'email.email' => $tmpUser, // сюда идет возрат данных по найденному пользователю
                ]);

            $v = $this->c->Test->beforeValidation($v);

            if ($v->validation($_POST, true)) {
                $key  = $this->c->Secury->randomPass(32);
                $hash = $this->c->Secury->hash($tmpUser->id . $key);
                $link = $this->c->Router->link(
                    'ChangePassword',
                    [
                        'id'   => $tmpUser->id,
                        'key'  => $key,
                        'hash' => $hash,
                    ]
                );
                $tplData = [
                    'fRootLink' => $this->c->Router->link('Index'),
                    'fMailer'   => __('Mailer', $this->c->config->o_board_title),
                    'username'  => $tmpUser->username,
                    'link'      => $link,
                ];

                try {
                    $isSent = $this->c->Mail
                        ->reset()
                        ->setMaxRecipients(1)
                        ->setFolder($this->c->DIR_LANG)
                        ->setLanguage($tmpUser->language)
                        ->setTo($tmpUser->email, $tmpUser->username)
                        ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                        ->setTpl('passphrase_reset.tpl', $tplData)
                        ->send();
                } catch (MailException $e) {
                    $isSent = false;
                }

                if ($isSent) {
                    $tmpUser->activate_string = $key;
                    $tmpUser->last_email_sent = \time();

                    $this->c->users->update($tmpUser);

                    return $this->c->Message->message(__('Forget mail', $this->c->config->o_admin_email), false, 200);
                } else {
                    return $this->c->Message->message(__('Error mail', $this->c->config->o_admin_email), true, 424);
                }
            }

            $this->fIswev = $v->getErrors();
        }

        $this->fIndex     = 'login';
        $this->nameTpl    = 'passphrase_reset';
        $this->onlinePos  = 'passphrase_reset';
        $this->robots     = 'noindex';
        $this->titles     = __('Passphrase reset');

        $email            = $v ? $v->email : ($args['_email'] ?? '');
        $this->form       = $this->formForget($email);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formForget(string $email): array
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
                            'maxlength' => '80',
                            'value'     => $email,
                            'caption'   => __('Email'),
                            'info'      => __('Passphrase reset info'),
                            'required'  => true,
                            'pattern'   => '.+@.+',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => __('Send email'),
//                    'accesskey' => 's',
                ],
            ],
        ];
    }

    /**
     * Смена кодовой фразы
     */
    public function changePass(array $args, string $method): Page
    {
        if (
            ! \hash_equals($args['hash'], $this->c->Secury->hash($args['id'] . $args['key']))
            || ! ($user = $this->c->users->load((int) $args['id'])) instanceof User
            || $user->isGuest
            || empty($user->activate_string)
            || ! \hash_equals($user->activate_string, $args['key'])
        ) {
            // что-то пошло не так
            return $this->c->Message->message('Bad request', false);
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('auth');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:ChangePassword',
                    'password'  => 'required|string|min:16|password',
                    'password2' => 'required|same:password',
                    'submit'    => 'required|string',
                ])->addAliases([
                    'password'  => 'New pass',
                    'password2' => 'Confirm new pass',
                ])->addArguments([
                    'token' => $args,
                ])->addMessages([
                    'password.password'  => 'Pass format',
                    'password2.same'     => 'Pass not match',
                ]);

            $v = $this->c->Test->beforeValidation($v);

            if ($v->validation($_POST, true)) {
                $user->password        = \password_hash($v->password, \PASSWORD_DEFAULT);
                $user->email_confirmed = 1;
                $user->activate_string = '';

                $this->c->users->update($user);

                $this->fIswev = ['s', __('Pass updated')];

                return $this->login([], 'GET');
            }

            $this->fIswev = $v->getErrors();
        }
        // активация аккаунта (письмо активации не дошло, заказали восстановление)
        if ($user->isUnverified) {
            $user->group_id        = $this->c->config->o_default_user_group;
            $user->email_confirmed = 1;

            $this->c->users->update($user);

            $this->fIswev = ['i', __('Account activated')];
        }

        $this->fIndex     = 'login';
        $this->nameTpl    = 'change_passphrase';
        $this->onlinePos  = 'change_passphrase';
        $this->robots     = 'noindex';
        $this->titles     = __('Passphrase reset');
        $this->form       = $this->formChange($args);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formChange(array $args): array
    {
        return [
            'action' => $this->c->Router->link(
                'ChangePassword',
                $args
            ),
            'hidden' => [
                'token' => $this->c->Csrf->create(
                    'ChangePassword',
                    $args
                ),
            ],
            'sets'   => [
                'forget' => [
                    'fields' => [
                        'password' => [
                            'autofocus' => true,
                            'type'      => 'password',
                            'caption'   => __('New pass'),
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                        'password2' => [
                            'autofocus' => true,
                            'type'      => 'password',
                            'caption'   => __('Confirm new pass'),
                            'info'      => __('Pass format') . ' ' . __('Pass info'),
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'login' => [
                    'type'      => 'submit',
                    'value'     => __('Change passphrase'),
//                    'accesskey' => 's',
                ],
            ],
        ];
    }
}
