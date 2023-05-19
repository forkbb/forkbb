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
use function \ForkBB\__;

class Register extends Page
{
    use RegLogTrait;

    /**
     * Регистрация
     */
    public function reg(): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('register');

        $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'token'    => 'token:RegisterForm',
                'agree'    => 'required|token:Register',
                'on'       => 'integer',
                'email'    => 'required_with:on|string:trim|email:noban',
                'username' => 'required_with:on|string:trim|username|noURL:1',
                'password' => 'required_with:on|string|min:16|max:100000|password',
                'register' => 'required|string',
            ])->addAliases([
                'email'    => 'Email',
                'username' => 'Username',
                'password' => 'Passphrase',
            ])->addMessages([
                'agree.required'    => ['cancel', 'cancel'],
                'agree.token'       => [FORK_MESS_WARN, ['Bad agree', $this->c->Router->link('Register')]],
                'password.password' => 'Pass format',
                'username.login'    => 'Login format',
            ]);

        $v = $this->c->Test->beforeValidation($v);

        if ($v->validation($_POST, true)) {
            // завершение регистрации
            if (1 === $v->on) {
                $userInDB = $this->c->users->loadByEmail($v->email);

                if ($userInDB instanceof User) {
                    return $this->regDupe($v, $userInDB);
                }

                $id = $this->c->providerUser->findByEmail($v->email);

                if ($id > 0) {
                    $userInDB = $this->c->users->load($id);

                    if ($userInDB instanceof User) {
                        return $this->regDupe($v, $userInDB);
                    }
                }

                return $this->regEnd($v);
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

        $this->hhsLevel     = 'secure';
        $this->fIndex       = self::FI_REG;
        $this->nameTpl      = 'register';
        $this->onlinePos    = 'register';
        $this->onlineDetail = null;
        $this->titles       = 'Register';
        $this->robots       = 'noindex';
        $this->form         = $this->formReg($v);
        $this->formOAuth    = $this->reglogForm();

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formReg(Validator $v): array
    {
        return [
            'action' => $this->c->Router->link('RegisterForm'),
            'hidden' => [
                'token' => $this->c->Csrf->create('RegisterForm'),
                'agree' => $v->agree,
                'on'    => '1',
            ],
            'sets'   => [
                'reg' => [
                    'fields' => [
                        'email' => [
                            'autofocus' => true,
                            'class'     => ['hint'],
                            'type'      => 'email',
                            'maxlength' => (string) $this->c->MAX_EMAIL_LENGTH,
                            'value'     => $v->email,
                            'caption'   => 'Email',
                            'help'      => 'Email info',
                            'required'  => true,
                            'pattern'   => '.+@.+',
                        ],
                        'username' => [
                            'class'     => ['hint'],
                            'type'      => 'text',
                            'maxlength' => '25',
                            'value'     => $v->username,
                            'caption'   => 'Username',
                            'help'      => 'Login format',
                            'required'  => true,
                            'pattern'   => '^.{2,25}$',
                        ],
                        'password' => [
                            'class'     => ['hint'],
                            'type'      => 'password',
                            'caption'   => 'Passphrase',
                            'help'      => 'Passphrase help',
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'register' => [
                    'type'  => 'submit',
                    'value' => __('Sign up'),
                ],
            ],
        ];
    }

    /**
     * Завершение регистрации
     */
    protected function regEnd(Validator $v): Page
    {
        if (1 === $this->c->config->b_regs_verify) {
            $groupId = FORK_GROUP_UNVERIFIED;
            $key     = $this->c->Secury->randomPass(31);
        } else {
            $groupId = $this->c->config->i_default_user_group;
            $key     = '';
        }

        $user = $this->c->users->create();

        $user->username        = $v->username;
        $user->password        = \password_hash($v->password, \PASSWORD_DEFAULT);
        $user->group_id        = $groupId;
        $user->email           = $v->email;
        $user->email_confirmed = 0;
        $user->activate_string = $key;
        $user->u_mark_all_read = \time();
        $user->email_setting   = $this->c->config->i_default_email_setting;
        $user->timezone        = $this->c->config->o_default_timezone;
        $user->language        = $this->user->language;
        $user->style           = $this->user->style;
        $user->registered      = \time();
        $user->registration_ip = $this->user->ip;
        $user->signature       = '';

        $newUserId = $this->c->users->insert($user);

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
                'username'  => $v->username,
                'userLink'  => $this->c->Router->link(
                    'User',
                    [
                        'id'   => $newUserId,
                        'name' => $v->username,
                    ]
                ),
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
                    ->send();
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
        if (1 === $this->c->config->b_regs_verify) {
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
                'username'  => $v->username,
                'link'      => $link,
            ];

            try {
                $isSent = $this->c->Mail
                    ->reset()
                    ->setMaxRecipients(1)
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->user->language)
                    ->setTo($v->email)
                    ->setFrom($this->c->config->o_webmaster_email, $tplData['fMailer'])
                    ->setTpl('welcome.tpl', $tplData)
                    ->send();
            } catch (MailException $e) {
                $this->c->Log->error('Registration: MailException', [
                    'user'      => $user->fLog(),
                    'exception' => $e,
                    'headers'   => false,
                ]);
            }

            // письмо активации аккаунта отправлено
            if ($isSent) {
                return $this->c->Message->message(['Reg email', $this->c->config->o_admin_email], false, 200);
            // форма сброса пароля
            } else {
                $auth         = $this->c->Auth;
                $auth->fIswev = [FORK_MESS_WARN, ['Error welcom mail', $this->c->config->o_admin_email]];

                return $auth->forget([], 'GET', $v->email);
            }
        // форма логина
        } else {
            $auth         = $this->c->Auth;
            $auth->fIswev = [FORK_MESS_SUCC, 'Reg complete'];

            return $auth->login([], 'GET', $v->username);
        }
    }

    /**
     * Делает вид, что пользователь зарегистрирован (для предотвращения утечки email)
     */
    protected function regDupe(Validator $v, User $userInDB): Page
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
                'email'     => $v->eamil,
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
                    ->send();
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
        if (1 === $this->c->config->b_regs_verify) {
            $isSent = true;

            // письмо активации аккаунта отправлено
            if ($isSent) {
                return $this->c->Message->message(['Reg email', $this->c->config->o_admin_email], false, 200);
            // форма сброса пароля
            } else {
                $auth         = $this->c->Auth;
                $auth->fIswev = [FORK_MESS_WARN, ['Error welcom mail', $this->c->config->o_admin_email]];

                return $auth->forget([], 'GET', $v->email);
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
        if (
            ! $this->c->Csrf->verify($args['hash'], 'RegActivate', $args)
            || ! ($user = $this->c->users->load($args['id'])) instanceof User
            || ! \hash_equals($user->activate_string, $args['key'])
        ) {
            $this->c->Log->warning('Account activation: fail', [
                'user' => $user instanceof User ? $user->fLog() : $this->user->fLog(),
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
