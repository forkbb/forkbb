<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\RegLogTrait;
use ForkBB\Models\Provider\Driver;
use ForkBB\Models\User\User;
use function \ForkBB\__;

class Register extends Page
{
    use RegLogTrait;

    /**
     * Флаг входа с помощью OAuth
     */
    protected bool $useOAuth = false;

    /**
     * Регистрация
     */
    public function reg(array $args, string $method, ?Driver $provider = null): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('register');

        // регистрация через OAuth
        if (null !== $provider) {
            $this->provider = $provider;

            $_POST = [
                'token'    => $this->c->Csrf->create('RegisterForm'),
                'agree'    => $this->c->Csrf->create('Register'),
                'oauth'    => $this->providerToString($provider),
                'register' => 'Register with OAuth',
            ];

        // переход от Rules/завершение регистрации через OAuth
        } else {
            $v = $this->c->Validator->reset()->addRules(['oauth' => 'string']);

            if (
                ! $v->validation($_POST)
                || (
                    null !== $v->oauth
                    && ! ($this->provider = $this->stringToProvider($v->oauth)) instanceof Driver
                )
            ) {
                return $this->c->Message->message('Bad request');
            }
        }

        $this->useOAuth = $this->provider instanceof Driver;

        $rules = [
            'token'    => 'token:RegisterForm',
            'agree'    => 'required|token:Register',
            'on'       => 'integer',
            'oauth'    => 'string',
            'email'    => 'required_with:on|string:trim|email:noban',
            'username' => 'required_with:on|string:trim|username|noURL:1',
            'password' => 'required_with:on|string|min:16|max:100000|password',
            'terms'    => 'absent',
            'register' => 'required|string',
        ];

        if ($this->useOAuth) {
            unset($rules['email'], $rules['password']);
        }

        if (1 === $this->c->config->b_ant_use_js) {
            $rules['nekot'] = 'required_with:on|string|nekot';
        }

        $v = $this->c->Validator->reset()
            ->addValidators([])
            ->addRules($rules)
            ->addAliases([
                'email'    => 'Email',
                'username' => 'Username',
                'password' => 'Passphrase',
            ])->addMessages([
                'agree.required'    => ['cancel', 'cancel'],
                'agree.token'       => [FORK_MESS_WARN, ['Bad agree', $this->c->Router->link('Register')]],
                'password.password' => 'Pass format',
                'username.login'    => 'Login format',
                'nekot'             => [FORK_MESS_ERR, 'Javascript disabled or bot'],
            ]);

        $v = $this->c->Test->beforeValidation($v, true);

        if ($v->validation($_POST, true)) {
            // завершение регистрации
            if (1 === $v->on) {
                $email    = $this->useOAuth ? $this->provider->userEmail : $v->email;
                $userInDB = $this->c->users->loadByEmail($email);

                if ($userInDB instanceof User) {
                    return $this->regDupe($v, $userInDB, $email);
                }

                $id = $this->c->providerUser->findByEmail($email);

                if ($id > 0) {
                    $userInDB = $this->c->users->load($id);

                    if ($userInDB instanceof User) {
                        return $this->regDupe($v, $userInDB, $email);
                    }
                }

                return $this->regEnd($v, $email);
            }

        } else {
            $this->fIswev = $v->getErrors();

            $this->c->Log->warning('Registration: fail', [
                'user'   => $this->user->fLog(),
                'errors' => $v->getErrorsWithoutType(),
                'form'   => $v->getData(false, ['token', 'agree', 'password']),
            ]);

            // нет согласия с правилами
            if (isset($this->fIswev['cancel'])) {
                return $this->c->Message->message('Reg cancel', true, 403);
            }

            $this->httpStatus = 400;
        }

        $this->identifier   = 'register';
        $this->hhsLevel     = 'secure';
        $this->fIndex       = self::FI_REG;
        $this->nameTpl      = 'register';
        $this->onlinePos    = 'register';
        $this->onlineDetail = null;
        $this->titles       = 'Register';
        $this->robots       = 'noindex';
        $this->form         = $this->formReg($v);
        $this->formOAuth    = $this->useOAuth ? null : $this->reglogForm('reg');

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formReg(Validator $v): array
    {
        $form = [
            'action'  => $this->c->Router->link('RegisterForm'),
            'enctype' => 'multipart/form-data',
            'hidden'  => [
                'token' => $this->c->Csrf->create('RegisterForm'),
                'agree' => $v->agree,
                'on'    => '1',
            ],
            'sets'    => [],
            'btns'    => [
                'register' => [
                    'type'  => 'submit',
                    'value' => __('Sign up'),
                ],
            ],
        ];

        $fields = [];

        if (! $this->useOAuth) {
            $fields['email'] = [
                'autofocus'      => true,
                'class'          => ['hint'],
                'type'           => 'text',
                'maxlength'      => (string) $this->c->MAX_EMAIL_LENGTH,
                'value'          => $v->email,
                'caption'        => 'Email',
                'help'           => 1 === $this->c->config->b_regs_verify ? 'Email help2' : 'Email help',
                'required'       => true,
                'pattern'        => '^.*[^@]@[^@].*$',
                'autocapitalize' => 'off',
            ];
        }

        $fields['username'] = [
            'class'     => ['hint'],
            'type'      => 'text',
            'minlength' => $this->c->USERNAME['min'],
            'maxlength' => $this->c->USERNAME['max'],
            'value'     => $v->username ?? ($this->useOAuth ? $this->nameGenerator($this->provider) : ''),
            'caption'   => 'Username',
            'help'      => 'Login format',
            'required'  => true,
            'pattern'   => $this->c->USERNAME['jsPattern'],
        ];

        if (! $this->useOAuth) {
            $fields['password'] = [
                'class'     => ['hint'],
                'type'      => 'password',
                'caption'   => 'Passphrase',
                'help'      => 'Passphrase help',
                'required'  => true,
                'minlength' => '16',
                'pattern'   => '^.*[^ ] [^ ].*$',
            ];
        }

        if (1 === $this->c->config->b_ant_hidden_ch) {
            $fields['terms'] = [
                'type'    => 'checkbox',
                'label'   => 'I agree to the Terms of Use',
                'checked' => false,
            ];
        }

        $form['sets']['reg']['fields'] = $fields;

        if ($this->useOAuth) {
            $form['hidden']['oauth'] = $v->oauth;
        }

        if (1 === $this->c->config->b_ant_use_js) {
            $form['hidden']['nekot'] = '';
        }

        return $form;
    }

    /**
     * Завершение регистрации
     */
    protected function regEnd(Validator $v, string $email): Page
    {
        if (
            ! $this->useOAuth
            && 1 === $this->c->config->b_regs_verify
        ) {
            $groupId = FORK_GROUP_UNVERIFIED;
            $key     = $this->c->Secury->randomPass(31);

        } else {
            $groupId = $this->c->config->i_default_user_group;
            $key     = '';
        }

        $user = $this->c->users->create();

        $user->username        = $v->username;
        $user->password        = $this->useOAuth ? 'oauth_' . $this->c->Secury->randomPass(7) : \password_hash($v->password, \PASSWORD_DEFAULT);
        $user->group_id        = $groupId;
        $user->email           = $email;
        $user->email_confirmed = $this->useOAuth && $this->provider->userEmailVerifed ? 1 : 0;
        $user->activate_string = $key;
        $user->u_mark_all_read = \time();
        $user->email_setting   = $this->c->config->i_default_email_setting;
        $user->timezone        = $this->c->config->o_default_timezone;
        $user->language        = $this->user->language;
        $user->locale          = $this->user->language;
        $user->style           = $this->user->style;
        $user->registered      = \time();
        $user->registration_ip = $this->user->ip;
        $user->signature       = '';
        $user->ip_check_type   = 0;
        $user->location        = $this->useOAuth ? $this->provider->userLocation : '';
        $user->url             = $this->useOAuth ? $this->provider->userURL : '';
        $user->admin_note      = $this->user->isBot ? '{bot reg}' : '';

        if (
            $this->useOAuth
            && $this->provider->userAvatar
        ) {
            $image = $this->c->Files->uploadFromLink($this->provider->userAvatar);

            if ($image instanceof Image) {
                $name   = $this->c->Secury->randomPass(8);
                $path   = $this->c->DIR_PUBLIC . "{$this->c->config->o_avatars_dir}/{$name}.(webp|jpg|png|gif)";
                $result = $image
                    ->rename(true)
                    ->rewrite(false)
                    ->resize($this->c->config->i_avatars_width, $this->c->config->i_avatars_height)
                    ->toFile($path, $this->c->config->i_avatars_size);

                if (true === $result) {
                    $user->avatar = $image->name() . '.' . $image->ext();

                } else {
                    $this->c->Log->warning('OAuth Failed image processing', [
                        'user'  => $user->fLog(),
                        'error' => $image->error(),
                    ]);
                }

            } else {
                $this->c->Log->warning('OAuth Avatar not image', [
                    'user'  => $user->fLog(),
                    'error' => $this->c->Files->error(),
                ]);
            }
        }

        $newUserId = $this->c->users->insert($user);

        if (
            $this->useOAuth
            && true !== $this->c->providerUser->registration($user, $this->provider)
        ) {
            throw new RuntimeException('Failed to insert data'); // ??????????????????????????????????????????
        }

        $this->c->Log->info('Registriaton: ok', [
            'user'    => $user->fLog(),
            'form'    => $v->getData(false, ['token', 'agree', 'password']),
            'headers' => true,
        ]);

        // уведомление о регистрации
        if (
            1 === $this->c->config->b_regs_report
            && '' != $this->c->config->o_mailing_list
        ) {
            $this->c->Lang->load('common', $this->c->config->o_default_lang);

            $tplData = [
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer'   => __(['Mailer', $this->c->config->o_board_title]),
                'username'  => $user->username,
                'userLink'  => $user->link,
            ];

            try {
                $this->c->Mail
                    ->reset()
                    ->setMaxRecipients((int) $this->c->config->i_email_max_recipients)
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->c->config->o_default_lang)
                    ->setTo($this->c->config->o_mailing_list)
                    ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                    ->setTpl('new_user.tpl', $tplData)
                    ->send(8);
            } catch (MailException $e) {
                $this->c->Log->error('Registration: notification to admins, MailException', [
                    'exception' => $e,
                    'headers'   => false,
                ]);
            }
        }

        $this->c->Lang->load('common', $this->user->language);
        $this->c->Lang->load('register');

        // отправка письма активации аккаунта
        if (
            ! $this->useOAuth
            && 1 === $this->c->config->b_regs_verify
        ) {
            $this->c->Csrf->setHashExpiration(259200); // ???? хэш действует 72 часа

            $link = $this->c->Router->link(
                'RegActivate',
                [
                    'id'   => $newUserId,
                    'key'  => $key,
                ]
            );
            $tplData = [
                'fTitle'    => $this->c->config->o_board_title,
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer'   => __(['Mailer', $this->c->config->o_board_title]),
                'username'  => $user->username,
                'link'      => $link,
            ];

            try {
                $isSent = $this->c->Mail
                    ->reset()
                    ->setMaxRecipients(1)
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->user->language)
                    ->setTo($email)
                    ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                    ->setTpl('welcome.tpl', $tplData)
                    ->send(9);
            } catch (MailException $e) {
                $this->c->Log->error('Registration: MailException', [
                    'user'      => $user->fLog(),
                    'exception' => $e,
                    'headers'   => false,
                ]);

                $isSent = false;
            }

            // письмо активации аккаунта отправлено
            if ($isSent) {
                return $this->c->Message->message(['Reg email', $this->c->config->o_admin_email], false, 200);

            // форма сброса пароля
            } else {
                $auth         = $this->c->Auth;
                $auth->fIswev = [FORK_MESS_WARN, ['Error welcom mail', $this->c->config->o_admin_email]];

                return $auth->forget([], 'GET', $email);
            }

        // форма логина
        } else {
            return $this->c->Auth->login([], 'POST', '', $user);
/*
            $auth         = $this->c->Auth;
            $auth->fIswev = [FORK_MESS_SUCC, 'Reg complete'];

            return $auth->login([], 'GET', $v->username);
*/
        }
    }

    /**
     * Делает вид, что пользователь зарегистрирован (для предотвращения утечки email)
     */
    protected function regDupe(Validator $v, User $userInDB, string $email): Page
    {
        $this->c->Log->warning('Registriaton: dupe', [
            'user'     => $this->user->fLog(), // ????
            'attacked' => $userInDB->fLog(),
            'form'     => $v->getData(false, ['token', 'agree', 'password']),
        ]);

        // уведомление о дубликате email
        if (
            1 === $this->c->config->b_regs_report
            && '' != $this->c->config->o_mailing_list
        ) {
            $this->c->Lang->load('common', $this->c->config->o_default_lang);

            $tplData = [
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer'   => __(['Mailer', $this->c->config->o_board_title]),
                'username'  => $v->username,
                'email'     => $v->email,
                'ip'        => $this->user->ip,
                'userInDB'  => $userInDB->username,
            ];

            try {
                $this->c->Mail
                    ->reset()
                    ->setMaxRecipients((int) $this->c->config->i_email_max_recipients)
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->c->config->o_default_lang)
                    ->setTo($this->c->config->o_mailing_list)
                    ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                    ->setTpl('dupe_email_register.tpl', $tplData)
                    ->send(8);
            } catch (MailException $e) {
                $this->c->Log->error('Registration: notification to admins, MailException', [
                    'exception' => $e,
                    'headers'   => false,
                ]);
            }
        }

        $this->c->Lang->load('common', $this->user->language);
        $this->c->Lang->load('register');

        // фейк отправки письма активации аккаунта
        if (
            ! $this->useOAuth
            && 1 === $this->c->config->b_regs_verify
        ) {
            $isSent = true;

            // отправка письма на сброс кодовой фразы
            if (\time() - $userInDB->last_email_sent >= $this->c->FLOOD_INTERVAL) {
                $this->c->Auth->forget([], 'POST', '', $userInDB);
            }

            // письмо активации аккаунта отправлено
            if ($isSent) {
                return $this->c->Message->message(['Reg email', $this->c->config->o_admin_email], false, 200);

            // форма сброса пароля
            } else {
                $auth         = $this->c->Auth;
                $auth->fIswev = [FORK_MESS_WARN, ['Error welcom mail', $this->c->config->o_admin_email]];

                return $auth->forget([], 'GET', $email);
            }

        // форма логина
        } else {
            $auth         = $this->c->Auth;
            $auth->fIswev = [FORK_MESS_SUCC, 'Reg complete'];

            return $auth->login([], 'GET', $v->username);
        }
    }

    /**
     * Активация аккаунта
     */
    public function activate(array $args): Page
    {
        $user = null;

        if (
            ! $this->c->Csrf->verify($args['hash'], 'RegActivate', $args)
            || ! ($user = $this->c->users->load($args['id'])) instanceof User
            || ! \hash_equals($user->activate_string, $args['key'])
        ) {
            $this->c->Log->warning('Account activation: fail', [
                'user' => isset($user) && $user instanceof User ? $user->fLog() : $this->user->fLog(),
                'args' => $args,
            ]);

            return $this->c->Message->message('Bad request', false);
        }

        $user->group_id        = $this->c->config->i_default_user_group;
        $user->email_confirmed = 1;
        $user->activate_string = '';

        $this->c->users->update($user);

        $this->c->Log->info('Account activation: ok', [
            'user' => $user->fLog(),
        ]);

        $this->c->Lang->load('register');

        $auth         = $this->c->Auth;
        $auth->fIswev = [FORK_MESS_SUCC, 'Reg complete'];

        return $auth->login([], 'GET', $user->username);
    }
}
