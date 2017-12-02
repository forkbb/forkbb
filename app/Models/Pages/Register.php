<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User;

class Register extends Page
{
    /**
     * Обработчик регистрации
     * 
     * @return Page
     */
    public function reg()
    {
        $this->c->Lang->load('register');

        $v = $this->c->Validator->addValidators([
            'check_email'    => [$this, 'vCheckEmail'],
            'check_username' => [$this, 'vCheckUsername'],
        ])->setRules([
            'token'    => 'token:RegisterForm',
            'agree'    => 'required|token:Register',
            'on'       => 'integer',
            'email'    => ['required_with:on|string:trim,lower|email|check_email', __('Email')],
            'username' => ['required_with:on|string:trim,spaces|min:2|max:25|login|check_username', __('Username')],
            'password' => ['required_with:on|string|min:16|password', __('Passphrase')],
        ])->setMessages([
            'agree.required'    => ['cancel', 'cancel'],
            'agree.token'       => [__('Bad agree', $this->c->Router->link('Register')), 'w'],
            'password.password' => __('Pass format'),
            'username.login'    => __('Login format'),
        ]);

        // завершение регистрации
        if ($v->validation($_POST) && $v->on === 1) {
            return $this->regEnd($v);
        }

        $this->fIswev = $v->getErrors();

        // нет согласия с правилами
        if (isset($this->fIswev['cancel'])) {
            return $this->c->Redirect->page('Index')->message(__('Reg cancel redirect'));
        }

        $this->fIndex     = 'register';
        $this->nameTpl    = 'register';
        $this->onlinePos  = 'register';
        $this->titles     = __('Register');
        $this->robots     = 'noindex';
        $this->formAction = $this->c->Router->link('RegisterForm');
        $this->formToken  = $this->c->Csrf->create('RegisterForm');
        $this->agree      = $v->agree;
        $this->on         = '1';
        $this->email      = $v->email;
        $this->username   = $v->username;

        return $this;
    }

    /**
     * Дополнительная проверка email
     * 
     * @param Validator $v
     * @param string $email
     * 
     * @return array
     */
    public function vCheckEmail(Validator $v, $email)
    {
        $error = false;
        $user = $this->c->ModelUser;
        $user->__email = $email;

        // email забанен
        if ($this->c->bans->isBanned($user) > 0) {
            $error = __('Banned email');
        // найден хотя бы 1 юзер с таким же email
        } elseif (empty($v->getErrors()) && $user->load($email, 'email') !== 0) {
            $error = __('Dupe email');
        }
        return [$email, $error];
    }

    /**
     * Дополнительная проверка username
     * 
     * @param Validator $v
     * @param string $username
     * 
     * @return array
     */
    public function vCheckUsername(Validator $v, $username)
    {
        $error = false;
        $user = $this->c->ModelUser;
        $user->__username = $username;

        // username = Гость
        if (preg_match('%^(guest|' . preg_quote(__('Guest'), '%') . ')$%iu', $username)) {
            $error = __('Username guest');
        // цензура
        } elseif ($this->c->censorship->censor($username) !== $username) {
            $error = __('Username censor');
        // username забанен
        } elseif ($this->c->bans->isBanned($user) > 0) {
            $error = __('Banned username');
        // есть пользователь с похожим именем
        } elseif (empty($v->getErrors()) && ! $user->isUnique()) {
            $error = __('Username not unique');
        }
        return [$username, $error];
    }

    /**
     * Завершение регистрации
     * 
     * @param array @data
     * 
     * @return Page
     */
    protected function regEnd(Validator $v)
    {
        if ($this->c->config->o_regs_verify == '1') {
            $groupId = $this->c->GROUP_UNVERIFIED;
            $key = 'w' . $this->c->Secury->randomPass(79);
        } else {
            $groupId = $this->c->config->o_default_user_group;
            $key = null;
        }

        $user = $this->c->ModelUser;
        $user->username        = $v->username;
        $user->password        = password_hash($v->password, PASSWORD_DEFAULT);
        $user->group_id        = $groupId;
        $user->email           = $v->email;
        $user->email_confirmed = 0;
        $user->activate_string = $key;
        $user->u_mark_all_read = time();
        $user->email_setting   = $this->c->config->o_default_email_setting;
        $user->timezone        = $this->c->config->o_default_timezone;
        $user->dst             = $this->c->config->o_default_dst;
        $user->language        = $user->language;
        $user->style           = $user->style;
        $user->registered      = time();
        $user->registration_ip = $this->c->user->ip;
            
        $newUserId = $user->insert();

        // обновление статистики по пользователям
        if ($this->c->config->o_regs_verify != '1') {
            $this->c->{'users_info update'};
        }

        // уведомление о регистрации
        if ($this->c->config->o_regs_report == '1' && $this->c->config->o_mailing_list != '') {
            $tplData = [
                'fTitle' => $this->c->config->o_board_title,
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer' => __('Mailer', $this->c->config->o_board_title),
                'username' => $v->username,
                'userLink' => $this->c->Router->link('User', ['id' => $newUserId, 'name' => $v->username]),
            ];

            try {
                $this->c->Mail
                    ->reset()
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->c->config->o_default_lang)
                    ->setTo($this->c->config->o_mailing_list)
                    ->setFrom($this->c->config->o_webmaster_email, __('Mailer', $this->c->config->o_board_title))
                    ->setTpl('new_user.tpl', $tplData)
                    ->send();
            } catch (MailException $e) {
            //????
            }
        }

        $this->c->Lang->load('register');

        // отправка письма активации аккаунта
        if ($this->c->config->o_regs_verify == '1') {
            $hash = $this->c->Secury->hash($newUserId . $key);
            $link = $this->c->Router->link('RegActivate', ['id' => $newUserId, 'key' => $key, 'hash' => $hash]);
            $tplData = [
                'fTitle' => $this->c->config->o_board_title,
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer' => __('Mailer', $this->c->config->o_board_title),
                'username' => $v->username,
                'link' => $link,
            ];

            try {
                $isSent = $this->c->Mail
                    ->reset()
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->c->user->language)
                    ->setTo($v->email)
                    ->setFrom($this->c->config->o_webmaster_email, __('Mailer', $this->c->config->o_board_title))
                    ->setTpl('welcome.tpl', $tplData)
                    ->send();
            } catch (MailException $e) {
                $isSent = false;
            }

            // письмо активации аккаунта отправлено
            if ($isSent) {
                return $this->c->Message->message(__('Reg email', $this->c->config->o_admin_email), false, 200);
            // форма сброса пароля
            } else {
                $auth = $this->c->Auth;
                $auth->fIswev = ['w' => [__('Error welcom mail', $this->c->config->o_admin_email)]];
                return $auth->forget(['_email' => $v->email]);
            }
        // форма логина
        } else {
            $auth = $this->c->Auth;
            $auth->fIswev = ['s' => [__('Reg complete')]];
            return $auth->login(['_username' => $v->username]);
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
        if (! hash_equals($args['hash'], $this->c->Secury->hash($args['id'] . $args['key']))
            || ! ($user = $this->c->ModelUser->load($args['id'])) instanceof User
            || empty($user->activate_string)
            || $user->activate_string{0} !== 'w'
            || ! hash_equals($user->activate_string, $args['key'])
        ) {
            return $this->c->Message->message(__('Bad request'), false);
        }

        $user->group_id = $this->c->config->o_default_user_group;
        $user->email_confirmed = 1;
        $user->activate_string = null;
        $user->update();
        $this->c->{'users_info update'};

        $this->c->Lang->load('register');

        $auth = $this->c->Auth;
        $auth->fIswev = ['s' => [__('Reg complete')]];
        return $auth->login(['_username' => $v->username]);
    }
}
