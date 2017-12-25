<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;

class Auth extends Page
{
    /**
     * Для передачи User из vCheckEmail() в forgetPost()
     * @var User
     */
    protected $tmpUser; //????

    /**
     * Выход пользователя
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function logout($args)
    {
        if (empty($args['token']) || ! $this->c->Csrf->verify($args['token'], 'Logout', $args)) {
            return $this->c->Redirect->page('Index')->message(\ForkBB\__('Bad token'));
        }

        $this->c->Cookie->deleteUser();
        $this->c->Online->delete($this->c->user);
        $this->c->users->updateLastVisit($this->c->user);

        $this->c->Lang->load('auth');
        return $this->c->Redirect->page('Index')->message(\ForkBB\__('Logout redirect'));
    }

    /**
     * Подготовка данных для страницы входа на форум
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function login(array $args)
    {
        $this->c->Lang->load('auth');

        $save = empty($args) || ! empty($args['_save']);

        if (! isset($args['_username'])) {
            $args['_username'] = '';
        }
        if (! isset($args['_redirect'])) {
            $args['_redirect'] = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
            $args['_redirect'] = $this->c->Router->validate($args['_redirect'], 'Index');
        }

        $this->fIndex     = 'login';
        $this->nameTpl    = 'login';
        $this->onlinePos  = 'login';
        $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Login');
        $this->formAction = $this->c->Router->link('Login');
        $this->formToken  = $this->c->Csrf->create('Login');
        $this->forgetLink = $this->c->Router->link('Forget');
        $this->regLink    = $this->c->config->o_regs_allow == '1' ? $this->c->Router->link('Register') : null;
        $this->username   = $args['_username'];
        $this->redirect   = $args['_redirect'];
        $this->save       = $save;

        return $this;
    }

    /**
     * Вход на форум
     * 
     * @return Page
     */
    public function loginPost()
    {
        $this->c->Lang->load('auth');

        $v = $this->c->Validator->addValidators([
            'login_process' => [$this, 'vLoginProcess'],
        ])->setRules([
            'token'    => 'token:Login',
            'redirect' => 'referer:Index',
            'username' => ['required|string', \ForkBB\__('Username')],
            'password' => ['required|string|login_process', \ForkBB\__('Passphrase')],
            'save'     => 'checkbox',
        ]);

        if ($v->validation($_POST)) {
            return $this->c->Redirect->url($v->redirect)->message(\ForkBB\__('Login redirect'));
        } else {
            $this->fIswev = $v->getErrors();
            return $this->login([
                '_username' => $v->username,
                '_redirect' => $v->redirect,
                '_save'     => $v->save,
            ]);
        }
    }

    /**
     * Проверка по базе и вход на форум
     * 
     * @param Validator $v
     * @param string $password
     * 
     * @return array
     */
    public function vLoginProcess(Validator $v, $password)
    {
        if (! empty($v->getErrors())) {
        } elseif (! ($user = $this->c->users->load($v->username, 'username')) instanceof User) {
            $v->addError('Wrong user/pass');
        } elseif ($user->isUnverified) {
            $v->addError('Account is not activated', 'w');
        } else {
            $authorized = false;
            $hash = $user->password;
            // For FluxBB by Visman 1.5.10.74 and above
            if (strlen($hash) == 40) {
                if (hash_equals($hash, sha1($password . $this->c->SALT1))) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $user->password = $hash;
                    $authorized = true;
                }
            } else {
                $authorized = password_verify($password, $hash);
            }
            // ошибка в пароле
            if (! $authorized) {
                $v->addError('Wrong user/pass');
            } else {
                // перезаписываем ip админа и модератора - Visman
                if ($user->isAdmMod
                    && $this->c->config->o_check_ip
                    && $user->registration_ip != $this->c->user->ip
                ) {
                    $user->registration_ip = $this->c->user->ip;
                }
                // изменения юзера в базе
                $this->c->users->update($user);

                $this->c->Online->delete($this->c->user);
                $this->c->Cookie->setUser($user, (bool) $v->save);
            }
        }
        return $password;
    }

    /**
     * Подготовка данных для страницы восстановления пароля
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function forget(array $args)
    {
        $this->c->Lang->load('auth');

        if (! isset($args['_email'])) {
            $args['_email'] = '';
        }

        $this->fIndex     = 'login';
        $this->nameTpl    = 'passphrase_reset';
        $this->onlinePos  = 'passphrase_reset';
        $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Passphrase reset');
        $this->formAction = $this->c->Router->link('Forget');
        $this->formToken  = $this->c->Csrf->create('Forget');
        $this->email      = $args['_email'];

        return $this;
    }

    /**
     * Отправка письма для восстановления пароля
     * 
     * @return Page
     */
    public function forgetPost()
    {
        $this->c->Lang->load('auth');

        $v = $this->c->Validator->addValidators([
            'check_email' => [$this, 'vCheckEmail'],
        ])->setRules([
            'token' => 'token:Forget',
            'email' => 'required|string:trim,lower|email|check_email',
        ])->setMessages([
            'email.email' => 'Invalid email',
        ]);

        if (! $v->validation($_POST)) {
            $this->fIswev = $v->getErrors();
            return $this->forget([
                '_email' => $v->email,
            ]);
        }

        $key = 'p' . $this->c->Secury->randomPass(79);
        $hash = $this->c->Secury->hash($v->email . $key);
        $link = $this->c->Router->link('ChangePassword', ['email' => $v->email, 'key' => $key, 'hash' => $hash]);
        $tplData = [
            'fRootLink' => $this->c->Router->link('Index'),
            'fMailer' => \ForkBB\__('Mailer', $this->c->config->o_board_title),
            'username' => $this->tmpUser->username,
            'link' => $link,
        ];

        try {
            $isSent = $this->c->Mail
                ->reset()
                ->setFolder($this->c->DIR_LANG)
                ->setLanguage($this->tmpUser->language)
                ->setTo($v->email, $this->tmpUser->username)
                ->setFrom($this->c->config->o_webmaster_email, \ForkBB\__('Mailer', $this->c->config->o_board_title))
                ->setTpl('passphrase_reset.tpl', $tplData)
                ->send();
        } catch (MailException $e) {
            $isSent = false;
        }

        if ($isSent) {
            $this->tmpUser->activate_string = $key;
            $this->tmpUser->last_email_sent = time();
            $this->c->users->update($this->tmpUser);
            return $this->c->Message->message(\ForkBB\__('Forget mail', $this->c->config->o_admin_email), false, 200);
        } else {
            return $this->c->Message->message(\ForkBB\__('Error mail', $this->c->config->o_admin_email), true, 200);
        }
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
        if (! empty($v->getErrors())) {
            return $email;
        }
            
        $user = $this->c->users->create();
        $user->__email = $email;

        // email забанен
        if ($this->c->bans->isBanned($user) > 0) {
            $v->addError('Banned email');
        // нет пользователя с таким email
        } elseif (! $user->load($email, 'email') instanceof User) {
            $v->addError('Invalid email');
        // за последний час уже был запрос на этот email
        } elseif (! empty($user->last_email_sent) && time() - $user->last_email_sent < 3600) {
            $v->addError(\ForkBB\__('Email flood', (int) (($user->last_email_sent + 3600 - time()) / 60)), 'e');
        } else {
            $this->tmpUser = $user;
        }
        return $email;
    }

    /**
     * Подготовка данных для формы изменения пароля
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function changePass(array $args)
    {
        if (isset($args['_user'])) {
            $user = $args['_user'];
            unset($args['_user']);
        } else {
            // что-то пошло не так
            if (! hash_equals($args['hash'], $this->c->Secury->hash($args['email'] . $args['key']))
                || ! ($user = $this->c->users->load($args['email'], 'email')) instanceof User
                || empty($user->activate_string)
                || $user->activate_string{0} !== 'p'
                || ! hash_equals($user->activate_string, $args['key'])
            ) {
                return $this->c->Message->message('Bad request', false);
            }
        }

        $this->c->Lang->load('auth');

        if ($user->isUnverified) {
            $user->group_id = $this->c->config->o_default_user_group;
            $user->email_confirmed = 1;
            $this->c->users->update($user);
            $this->c->{'users_info update'};
            $this->a['fIswev']['i'][] = \ForkBB\__('Account activated');
        }

        $this->fIndex     = 'login';
        $this->nameTpl    = 'change_passphrase';
        $this->onlinePos  = 'change_passphrase';
        $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Passphrase reset');
        $this->formAction = $this->c->Router->link('ChangePassword', $args);
        $this->formToken  = $this->c->Csrf->create('ChangePassword', $args);

        return $this;
    }

    /**
     * Смена пароля
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function changePassPost(array $args)
    {
        // что-то пошло не так
        if (! hash_equals($args['hash'], $this->c->Secury->hash($args['email'] . $args['key']))
            || ! ($user = $this->c->users->load($args['email'], 'email')) instanceof User
            || empty($user->activate_string)
            || $user->activate_string{0} !== 'p'
            || ! hash_equals($user->activate_string, $args['key'])
        ) {
            return $this->c->Message->message('Bad request', false);
        }

        $this->c->Lang->load('auth');

        $v = $this->c->Validator;
        $v->setRules([
            'token'     => 'token:ChangePassword',
            'password'  => ['required|string|min:16|password', \ForkBB\__('New pass')],
            'password2' => ['required|same:password', \ForkBB\__('Confirm new pass')],
        ])->setArguments([
            'token' => $args,
        ])->setMessages([
            'password.password'  => 'Pass format',
            'password2.same'     => 'Pass not match',
        ]);

        if (! $v->validation($_POST)) {
            $this->fIswev = $v->getErrors();
            $args['_user'] = $user;
            return $this->changePass($args);
        }
        $data = $v->getData();

        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->email_confirmed = 1;
        $user->activate_string = null;
        $this->c->users->update($user);

        $this->a['fIswev']['s'][] = \ForkBB\__('Pass updated');
        return $this->login(['_redirect' => $this->c->Router->link('Index')]);
    }
}
