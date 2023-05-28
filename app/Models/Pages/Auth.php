<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\RegLogTrait;
use ForkBB\Models\User\User;
use SensitiveParameter;
use function \ForkBB\__;

class Auth extends Page
{
    use RegLogTrait;

    /**
     * Флаг входа c использованием html формы
     */
    protected bool $loginWithForm = true;

    /**
     * Выход пользователя
     */
    public function logout(array $args): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'Logout', $args)) {
            $this->c->Log->warning('Logout: fail', [
                'user' => $this->user->fLog(),
            ]);

            return $this->c->Redirect->page('Index')->message($this->c->Csrf->getError(), FORK_MESS_ERR);
        }

        $this->c->Cookie->deleteUser();
        $this->c->Online->delete($this->user);
        $this->c->users->updateLastVisit($this->user);

        $this->c->Log->info('Logout: ok', [
            'user' => $this->user->fLog(),
        ]);

        $this->c->Lang->load('auth');

        return $this->c->Redirect->page('Index')->message('Logout redirect', FORK_MESS_SUCC);
    }

    /**
     * Вход на форум
     */
    public function login(array $args, string $method, string $username = '', User $user = null): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('auth');

        $v = null;

        if ('POST' === $method) {
            // вход без html формы
            if (null !== $user) {
                $this->userAfterLogin = $user;
                $this->loginWithForm  = false;

                $_POST = [
                    'token'    => $this->c->Csrf->create('Login'),
                    'redirect' => $this->c->Csrf->create('Index'),
                    'username' => $user->username,
                    'password' => $user->password,
                    'save'     => '1',
                    'login'    => 'Login User model',
                ];
            }

            $v = $this->c->Validator->reset()
                ->addValidators([
                    'login_check' => [$this, 'vLoginCheck'],
                ])->addRules([
                    'token'    => 'token:Login',
                    'redirect' => 'required|referer:Index',
                    'username' => 'required|string',
                    'password' => 'required|string|max:100000|login_check',
                    'save'     => 'checkbox',
                    'login'    => 'required|string',
                ])->addAliases([
                    'username' => 'Username',
                    'password' => 'Passphrase',
                ]);

            $v = $this->c->Test->beforeValidation($v);

            if ($v->validation($_POST, true)) {
                $this->loginEnd($v);

                return $this->c->Redirect->url($v->redirect)->message('Login redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();

            $this->c->Log->warning('Login: fail', [
                'user'   => $this->user->fLog(),
                'errors' => $v->getErrorsWithoutType(),
                'form'   => $v->getData(false, ['token', 'password']),
            ]);

            $this->httpStatus = 400;
        }

        $ref = $this->c->Secury->replInvalidChars($_SERVER['HTTP_REFERER'] ?? '');

        $this->hhsLevel     = 'secure';
        $this->fIndex       = self::FI_LOGIN;
        $this->nameTpl      = 'login';
        $this->onlinePos    = 'login';
        $this->onlineDetail = null;
        $this->robots       = 'noindex';
        $this->titles       = 'Login';
        $this->regLink      = 1 === $this->c->config->b_regs_allow ? $this->c->Router->link('Register') : null;

        $username           = $v->username ?? $username;
        $save               = $v->save ?? true;
        $redirect           = $v->redirect ?? $this->c->Router->validate($ref, 'Index');
        $this->form         = $this->formLogin($username, (bool) $save, $redirect);
        $this->formOAuth    = $this->reglogForm('auth');

        return $this;
    }

    /**
     * Обрабатывает вход пользователя
     */
    protected function loginEnd(Validator $v): void
    {
        $this->c->users->updateLoginIpCache($this->userAfterLogin, true); // ????

        // сбросить запрос на смену кодовой фразы
        if (32 === \strlen($this->userAfterLogin->activate_string)) {
            $this->userAfterLogin->activate_string = '';
        }
        // изменения юзера в базе
        $this->c->users->update($this->userAfterLogin);

        $this->c->Online->delete($this->user);
        $this->c->Cookie->setUser($this->userAfterLogin, (bool) $v->save);

        $this->c->Log->info('Login: ok', [
            'user'    => $this->userAfterLogin->fLog(),
            'form'    => $v->getData(false, ['token', 'password']),
            'headers' => true,
        ]);
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formLogin(string $username, bool $save, string $redirect): array
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
                            'caption'   => 'Username',
                            'required'  => true,
                        ],
                        'password' => [
                            'id'        => 'passinlogin',
                            'type'      => 'password',
                            'caption'   => 'Passphrase',
                            'help'      => ['<a href="%s">Forgotten?</a>', $this->c->Router->link('Forget')],
                            'required'  => true,
                        ],
                        'save' => [
                            'type'    => 'checkbox',
                            'label'   => 'Remember me',
                            'checked' => $save,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'login' => [
                    'type'  => 'submit',
                    'value' => __('Sign in'),
                ],
            ],
        ];
    }

    /**
     * Проверка пользователя по базе
     */
    public function vLoginCheck(Validator $v, #[SensitiveParameter] string $password ): string
    {
        if (empty($v->getErrors())) {
            if ($this->loginWithForm) {
                $this->userAfterLogin = $this->c->users->loadByName($v->username);
            }

            if (
                ! $this->userAfterLogin instanceof User
                || $this->userAfterLogin->isGuest
            ) {
                $v->addError('Wrong user/pass');
            } elseif ($this->userAfterLogin->isUnverified) {
                $v->addError('Account is not activated', FORK_MESS_WARN);
            } elseif (
                $this->loginWithForm
                && ! \password_verify($password, $this->userAfterLogin->password)
            ) {
                $v->addError('Wrong user/pass');
            }
        }

        if (! empty($v->getErrors())) {
            $this->userAfterLogin = null;
        }

        return $password;
    }

    /**
     * Запрос на смену кодовой фразы
     */
    public function forget(array $args, string $method, string $email = ''): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('auth');

        $v = null;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'  => 'token:Forget',
                    'email'  => 'required|string:trim|email',
                    'submit' => 'required|string',
                ])->addAliases([
                ])->addMessages([
                    'email.email' => 'Invalid email',
                ])->addArguments([
                ]);

            $v       = $this->c->Test->beforeValidation($v);
            $isValid = $v->validation($_POST, true);
            $context = [
                'user'    => $this->user->fLog(), // ???? Guest only?
                'errors'  => $v->getErrorsWithoutType(),
                'form'    => $v->getData(false, ['token']),
                'headers' => true,
            ];

            if ($isValid) {
                $tmpUser = $this->c->users->create();
                $isSent  = false;

                $v = $v->reset()
                    ->addRules([
                        'email' => 'required|string:trim|email:nosoloban,exists,flood',
                    ])->addArguments([
                        'email.email' => $tmpUser, // сюда идет возрат данных по найденному пользователю
                    ]);

                if (
                    $v->validation($_POST)
                    && 0 === $this->c->bans->banFromName($tmpUser->username)
                ) {
                    $this->c->Csrf->setHashExpiration(259200); // ???? хэш действует 72 часа

                    $key  = $this->c->Secury->randomPass(32);
                    $link = $this->c->Router->link(
                        'ChangePassword',
                        [
                            'id'   => $tmpUser->id,
                            'key'  => $key,
                        ]
                    );
                    $tplData = [
                        'fRootLink' => $this->c->Router->link('Index'),
                        'fMailer'   => __(['Mailer', $this->c->config->o_board_title]),
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
                        $this->c->Log->error('Passphrase reset: email form, MailException', [
                            'exception' => $e,
                            'headers'   => false,
                        ]);
                    }

                    if ($isSent) {
                        $tmpUser->activate_string = $key;
                        $tmpUser->last_email_sent = \time();

                        $this->c->users->update($tmpUser);

                        $this->c->Log->info('Passphrase reset: email form, ok', $context);
                    }
                }

                if (! $isSent) {
                    $context['errors'] = $v->getErrorsWithoutType();

                    $this->c->Log->warning('Passphrase reset: email form, fail', $context);
                }

                return $this->c->Message->message(['Forget mail', $this->c->config->o_admin_email], false, 0);
            }

            $this->fIswev = $v->getErrors();

            $this->c->Log->warning('Passphrase reset: email form, fail', $context);

            $this->httpStatus = 400;
        }

        $this->hhsLevel     = 'secure';
        $this->fIndex       = self::FI_LOGIN;
        $this->nameTpl      = 'passphrase_reset';
        $this->onlinePos    = 'passphrase_reset';
        $this->onlineDetail = null;
        $this->robots       = 'noindex';
        $this->titles       = 'Passphrase reset';
        $this->form         = $this->formForget($v->email ?? $email);

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
                            'autofocus'      => true,
                            'type'           => 'text',
                            'maxlength'      => (string) $this->c->MAX_EMAIL_LENGTH,
                            'value'          => $email,
                            'caption'        => 'Email',
                            'help'           => 'Passphrase reset info',
                            'required'       => true,
                            'pattern'        => '.+@.+',
                            'autocapitalize' => 'off',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('Send email'),
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
            ! $this->c->Csrf->verify($args['hash'], 'ChangePassword', $args)
            || ! ($user = $this->c->users->load($args['id'])) instanceof User
            || ! \hash_equals($user->activate_string, $args['key'])
        ) {
            $this->c->Log->warning('Passphrase reset: confirmation, fail', [
                'user' => $user instanceof User ? $user->fLog() : $this->user->fLog(),
                'args' => $args,
            ]);

            // что-то пошло не так
            return $this->c->Message->message('Bad request', false);
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('auth');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:ChangePassword',
                    'password'  => 'required|string|min:16|max:100000|password',
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

                $this->fIswev = [FORK_MESS_SUCC, 'Pass updated'];

                $this->c->Log->info('Passphrase reset: ok', [
                    'user' => $user->fLog(),
                ]);

                return $this->login([], 'GET');
            }

            $this->fIswev = $v->getErrors();

            $this->c->Log->warning('Passphrase reset: change form, fail', [
                'user'   => $user->fLog(),
                'errors' => $v->getErrorsWithoutType(),
                'form'   => $v->getData(false, ['token', 'password', 'password2']),
            ]);

            $this->httpStatus = 400;
        }
        // активация аккаунта (письмо активации не дошло, заказали восстановление)
        if ($user->isUnverified) {
            $user->group_id        = $this->c->config->i_default_user_group;
            $user->email_confirmed = 1;

            $this->c->users->update($user);

            $this->fIswev = [FORK_MESS_INFO, 'Account activated'];

            $this->c->Log->info('Account activation: ok', [
                'user' => $user->fLog(),
            ]);
        }

        $this->hhsLevel     = 'secure';
        $this->fIndex       = self::FI_LOGIN;
        $this->nameTpl      = 'change_passphrase';
        $this->onlinePos    = 'change_passphrase';
        $this->onlineDetail = null;
        $this->robots       = 'noindex';
        $this->titles       = 'Passphrase reset';
        $this->form         = $this->formChange($args);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formChange(array $args): array
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
                            'caption'   => 'New pass',
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                        'password2' => [
                            'type'      => 'password',
                            'caption'   => 'Confirm new pass',
                            'help'      => 'Passphrase help',
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('Change passphrase'),
                ],
            ],
        ];
    }
}
