<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;

class Register extends Page
{
    /**
     * Регистрация
     *
     * @return Page
     */
    public function reg()
    {
        $this->c->Lang->load('register');

        $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'token'    => 'token:RegisterForm',
                'agree'    => 'required|token:Register',
                'on'       => 'integer',
                'email'    => 'required_with:on|string:trim,lower|email:noban,unique',
                'username' => 'required_with:on|string:trim,spaces|username',
                'password' => 'required_with:on|string|min:16|password',
            ])->addAliases([
                'email'    => 'Email',
                'username' => 'Username',
                'password' => 'Passphrase',
            ])->addMessages([
                'agree.required'    => ['cancel', 'cancel'],
                'agree.token'       => [\ForkBB\__('Bad agree', $this->c->Router->link('Register')), 'w'],
                'password.password' => 'Pass format',
                'username.login'    => 'Login format',
            ]);

        // завершение регистрации
        if ($v->validation($_POST) && 1 === $v->on) {
            return $this->regEnd($v);
        }

        $this->fIswev = $v->getErrors();

        // нет согласия с правилами
        if (isset($this->fIswev['cancel'])) {
            return $this->c->Message->message('Reg cancel', true, 403);
        }

        $this->fIndex     = 'register';
        $this->nameTpl    = 'register';
        $this->onlinePos  = 'register';
        $this->titles     = \ForkBB\__('Register');
        $this->robots     = 'noindex';
        $this->form       = $this->formReg($v);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param Validator $v
     *
     * @return array
     */
    protected function formReg(Validator $v)
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
                            'class'     => 'hint',
                            'type'      => 'text',
                            'maxlength' => 80,
                            'value'     => $v->email,
                            'caption'   => \ForkBB\__('Email'),
                            'info'      => \ForkBB\__('Email info'),
                            'required'  => true,
                            'pattern'   => '.+@.+',
                        ],
                        'username' => [
                            'class'     => 'hint',
                            'type'      => 'text',
                            'maxlength' => 25,
                            'value'     => $v->username,
                            'caption'   => \ForkBB\__('Username'),
                            'info'      => \ForkBB\__('Login format'),
                            'required'  => true,
                            'pattern'   => '^.{2,25}$',
                        ],
                        'password' => [
                            'class'     => 'hint',
                            'type'      => 'password',
                            'caption'   => \ForkBB\__('Passphrase'),
                            'info'      => \ForkBB\__('Pass format') . ' ' . \ForkBB\__('Pass info'),
                            'required'  => true,
                            'pattern'   => '^.{16,}$',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'register' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Sign up'),
                    'accesskey' => 's',
                ],
            ],
        ];
    }

    /**
     * Завершение регистрации
     *
     * @param Validator $v
     *
     * @return Page
     */
    protected function regEnd(Validator $v)
    {
        if ('1' == $this->c->config->o_regs_verify) {
            $groupId = 0;
            $key     = $this->c->Secury->randomPass(31);
        } else {
            $groupId = $this->c->config->o_default_user_group;
            $key     = '';
        }

        $user = $this->c->users->create();

        $user->username        = $v->username;
        $user->password        = \password_hash($v->password, PASSWORD_DEFAULT);
        $user->group_id        = $groupId;
        $user->email           = $v->email;
        $user->email_confirmed = 0;
        $user->activate_string = $key;
        $user->u_mark_all_read = \time();
        $user->email_setting   = $this->c->config->o_default_email_setting;
        $user->timezone        = $this->c->config->o_default_timezone;
        $user->dst             = $this->c->config->o_default_dst;
        $user->language        = $this->user->language;
        $user->style           = $this->user->style;
        $user->registered      = \time();
        $user->registration_ip = $this->user->ip;

        $newUserId = $this->c->users->insert($user);

        // уведомление о регистрации
        if ('1' == $this->c->config->o_regs_report && '' != $this->c->config->o_mailing_list) {
            $tplData = [
                'fTitle' => $this->c->config->o_board_title,
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer' => \ForkBB\__('Mailer', $this->c->config->o_board_title),
                'username' => $v->username,
                'userLink' => $this->c->Router->link('User', ['id' => $newUserId, 'name' => $v->username]),
            ];

            try {
                $this->c->Mail
                    ->reset()
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->c->config->o_default_lang)
                    ->setTo($this->c->config->o_mailing_list)
                    ->setFrom($this->c->config->o_webmaster_email, \ForkBB\__('Mailer', $this->c->config->o_board_title))
                    ->setTpl('new_user.tpl', $tplData)
                    ->send();
            } catch (MailException $e) {
            //????
            }
        }

        $this->c->Lang->load('register');

        // отправка письма активации аккаунта
        if ('1' == $this->c->config->o_regs_verify) {
            $hash = $this->c->Secury->hash($newUserId . $key);
            $link = $this->c->Router->link('RegActivate', ['id' => $newUserId, 'key' => $key, 'hash' => $hash]);
            $tplData = [
                'fTitle' => $this->c->config->o_board_title,
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer' => \ForkBB\__('Mailer', $this->c->config->o_board_title),
                'username' => $v->username,
                'link' => $link,
            ];

            try {
                $isSent = $this->c->Mail
                    ->reset()
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->user->language)
                    ->setTo($v->email)
                    ->setFrom($this->c->config->o_webmaster_email, \ForkBB\__('Mailer', $this->c->config->o_board_title))
                    ->setTpl('welcome.tpl', $tplData)
                    ->send();
            } catch (MailException $e) {
                $isSent = false;
            }

            // письмо активации аккаунта отправлено
            if ($isSent) {
                return $this->c->Message->message(\ForkBB\__('Reg email', $this->c->config->o_admin_email), false, 200);
            // форма сброса пароля
            } else {
                $auth = $this->c->Auth;
                $auth->fIswev = ['w', \ForkBB\__('Error welcom mail', $this->c->config->o_admin_email)];
                return $auth->forget(['_email' => $v->email], 'GET');
            }
        // форма логина
        } else {
            $auth = $this->c->Auth;
            $auth->fIswev = ['s', \ForkBB\__('Reg complete')];
            return $auth->login(['_username' => $v->username], 'GET');
        }
    }

    /**
     * Активация аккаунта
     *
     * @param array $args
     *
     * @return Page
     */
    public function activate(array $args)
    {
        if (! \hash_equals($args['hash'], $this->c->Secury->hash($args['id'] . $args['key']))
            || ! ($user = $this->c->users->load($args['id'])) instanceof User
            || $user->isGuest
            || empty($user->activate_string)
            || ! \hash_equals($user->activate_string, $args['key'])
        ) {
            return $this->c->Message->message('Bad request', false);
        }

        $user->group_id        = $this->c->config->o_default_user_group;
        $user->email_confirmed = 1;
        $user->activate_string = '';

        $this->c->users->update($user);

        $this->c->Lang->load('register');

        $auth = $this->c->Auth;
        $auth->fIswev = ['s', \ForkBB\__('Reg complete')];
        return $auth->login(['_username' => $user->username], 'GET');
    }
}
