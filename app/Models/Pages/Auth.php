<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\User;

class Auth extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'login';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = 'login';

    /**
     * Указатель на активный пункт навигации
     * @var string
     */
    protected $index = 'login';

    /**
     * Для передачи User из vCheckEmail() в forgetPost()
     * @var User
     */
    protected $tmpUser;

    /**
     * Переменная для meta name="robots"
     * @var string
     */
    protected $robots = 'noindex';

    /**
     * Выход пользователя
     * @param array $args
     * @retrun Page
     */
    public function logout($args)
    {
        if (! $this->c->Csrf->verify($args['token'], 'Logout', $args)) {
            return $this->c->Redirect->setPage('Index')->setMessage(__('Bad token'));
        }

        $this->c->UserCookie->deleteUserCookie();
        $this->c->Online->delete($this->c->user);
        $this->c->UserMapper->updateLastVisit($this->c->user);

        $this->c->Lang->load('auth');
        return $this->c->Redirect->setPage('Index')->setMessage(__('Logout redirect'));
    }

    /**
     * Подготовка данных для страницы входа на форум
     * @param array $args
     * @return Page
     */
    public function login(array $args)
    {
        $this->c->Lang->load('auth');

        if (! isset($args['_username'])) {
            $args['_username'] = '';
        }
        if (! isset($args['_redirect'])) {
            $args['_redirect'] = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
            $args['_redirect'] = $this->c->Router->validate($args['_redirect'], 'Index');
        }

        $this->titles = [
            __('Login'),
        ];
        $this->data = [
            'formAction' => $this->c->Router->link('Login'),
            'formToken' => $this->c->Csrf->create('Login'),
            'forgetLink' => $this->c->Router->link('Forget'),
            'regLink' => $this->config['o_regs_allow'] == '1'
                ? $this->c->Router->link('Register')
                : null,
            'username' => $args['_username'],
            'redirect' => $args['_redirect'],
            'save' => ! empty($args['_save'])
        ];

        return $this;
    }

    /**
     * Вход на форум
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
            'username' => ['required|string', __('Username')],
            'password' => ['required|string|login_process', __('Passphrase')],
            'save'     => 'checkbox',
        ]);

        if ($v->validation($_POST)) {
            return $this->c->Redirect->setUrl($v->redirect)->setMessage(__('Login redirect'));
        } else {
            $this->iswev = $v->getErrors();
            return $this->login([
                '_username' => $v->username,
                '_redirect' => $v->redirect,
                '_save'     => $v->save,
            ]);
        }
    }

    /**
     * Проверка по базе и вход на форум
     * @param Validator $v
     * @param string $password
     * @param int $type
     * @return array
     */
    public function vLoginProcess(Validator $v, $password, $type)
    {
        $error = false;
        if (! empty($v->getErrors())) {
        } elseif (! ($user = $this->c->UserMapper->getUser($v->username, 'username')) instanceof User) {
            $error = __('Wrong user/pass');
        } elseif ($user->isUnverified) {
            $error = [__('Account is not activated'), 'w'];
        } else {
            $authorized = false;
            $hash = $user->password;
            $update = [];
            // For FluxBB by Visman 1.5.10.74 and above
            if (strlen($hash) == 40) {
                if (hash_equals($hash, sha1($password . $this->c->SALT1))) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $update['password'] = $hash;
                    $authorized = true;
                }
            } else {
                $authorized = password_verify($password, $hash);
            }
            // ошибка в пароле
            if (! $authorized) {
                $error = __('Wrong user/pass');
            } else {
                // перезаписываем ip админа и модератора - Visman
                if ($user->isAdmMod
                    && $this->config['o_check_ip']
                    && $user->registrationIp != $this->c->user->ip
                ) {
                    $update['registration_ip'] = $this->c->user->ip;
                }
                // изменения юзера в базе
                $this->c->UserMapper->updateUser($user->id, $update);

                $this->c->Online->delete($this->c->user);
                $this->c->UserCookie->setUserCookie($user->id, $hash, $v->save);
            }
        }
        return [$password, $type, $error];
    }

    /**
     * Подготовка данных для страницы восстановления пароля
     * @param array $args
     * @return Page
     */
    public function forget(array $args)
    {
        $this->nameTpl = 'passphrase_reset';
        $this->onlinePos = 'passphrase_reset';

        if (! isset($args['_email'])) {
            $args['_email'] = '';
        }

        $this->c->Lang->load('auth');

        $this->titles = [
            __('Passphrase reset'),
        ];
        $this->data = [
            'formAction' => $this->c->Router->link('Forget'),
            'formToken' => $this->c->Csrf->create('Forget'),
            'email' => $args['_email'],
        ];

        return $this;
    }

    /**
     * Отправка письма для восстановления пароля
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
            'email.email' => __('Invalid email'),
        ]);

        if (! $v->validation($_POST)) {
            $this->iswev = $v->getErrors();
            return $this->forget([
                '_email' => $v->email,
            ]);
        }

        $key = 'p' . $this->c->Secury->randomPass(79);
        $hash = $this->c->Secury->hash($v->email . $key);
        $link = $this->c->Router->link('ChangePassword', ['email' => $v->email, 'key' => $key, 'hash' => $hash]);
        $tplData = [
            'fRootLink' => $this->c->Router->link('Index'),
            'fMailer' => __('Mailer', $this->config['o_board_title']),
            'username' => $this->tmpUser->username,
            'link' => $link,
        ];

        try {
            $isSent = $this->c->Mail
                ->reset()
                ->setFolder($this->c->DIR_LANG)
                ->setLanguage($this->tmpUser->language)
                ->setTo($v->email, $this->tmpUser->username)
                ->setFrom($this->config['o_webmaster_email'], __('Mailer', $this->config['o_board_title']))
                ->setTpl('passphrase_reset.tpl', $tplData)
                ->send();
        } catch (MailException $e) {
            $isSent = false;
        }

        if ($isSent) {
            $this->c->UserMapper->updateUser($this->tmpUser->id, ['activate_string' => $key, 'last_email_sent' => time()]);
            return $this->c->Message->message(__('Forget mail', $this->config['o_admin_email']), false, 200);
        } else {
            return $this->c->Message->message(__('Error mail', $this->config['o_admin_email']), true, 200);
        }
    }

    /**
     * Дополнительная проверка email
     * @param Validator $v
     * @param string $username
     * @param int $type
     * @return array
     */
    public function vCheckEmail(Validator $v, $email, $type)
    {
        $error = false;
        // есть ошибки
        if (! empty($v->getErrors())) {
        // email забанен
        } elseif ($this->c->CheckBans->isBanned(null, $email) > 0) {
            $error = __('Banned email');
        // нет пользователя с таким email
        } elseif (! ($user = $this->c->UserMapper->getUser($email, 'email')) instanceof User) {
            $error = __('Invalid email');
        // за последний час уже был запрос на этот email
        } elseif (! empty($user->lastEmailSent) && time() - $user->lastEmailSent < 3600) {
            $error = [__('Email flood', (int) (($user->lastEmailSent + 3600 - time()) / 60)), 'e'];
        } else {
            $this->tmpUser = $user;
        }
        return [$email, $type, $error];
    }

    /**
     * Подготовка данных для формы изменения пароля
     * @param array $args
     * @return Page
     */
    public function changePass(array $args)
    {
        $this->nameTpl = 'change_passphrase';
        $this->onlinePos = 'change_passphrase';

        if (isset($args['_ok'])) {
            unset($args['_ok']);
        } else {
            // что-то пошло не так
            if (! hash_equals($args['hash'], $this->c->Secury->hash($args['email'] . $args['key']))
                || ! ($user = $this->c->UserMapper->getUser($args['email'], 'email')) instanceof User
                || empty($user->activateString)
                || $user->activateString{0} !== 'p'
                || ! hash_equals($user->activateString, $args['key'])
            ) {
                return $this->c->Message->message(__('Bad request'), false);
            }
        }

        $this->c->Lang->load('auth');

        if ($user->isUnverified) {
            $this->c->UserMapper->updateUser($user->id, ['group_id' => $this->config['o_default_user_group'], 'email_confirmed' => 1]);
            $this->c->{'users_info update'};
            $this->iswev['i'][] = __('Account activated');
        }

        $this->titles = [
            __('Change pass'),
        ];
        $this->data = [
            'formAction' => $this->c->Router->link('ChangePassword', $args),
            'formToken' => $this->c->Csrf->create('ChangePassword', $args),
        ];

        return $this;
    }

    /**
     * Смена пароля
     * @param array $args
     * @return Page
     */
    public function changePassPost(array $args)
    {
        // что-то пошло не так
        if (! hash_equals($args['hash'], $this->c->Secury->hash($args['email'] . $args['key']))
            || ! ($user = $this->c->UserMapper->getUser($args['email'], 'email')) instanceof User
            || empty($user->activateString)
            || $user->activateString{0} !== 'p'
            || ! hash_equals($user->activateString, $args['key'])
        ) {
            return $this->c->Message->message(__('Bad request'), false);
        }

        $this->c->Lang->load('auth');

        $v = $this->c->Validator;
        $v->setRules([
            'token'     => 'token:ChangePassword',
            'password'  => ['required|string|min:16|password', __('New pass')],
            'password2' => ['required|same:password', __('Confirm new pass')],
        ])->setArguments([
            'token' => $args,
        ])->setMessages([
            'password.password'  => __('Pass format'),
            'password2.same' => __('Pass not match'),
        ]);

        if (! $v->validation($_POST)) {
            $this->iswev = $v->getErrors();
            $args['_ok'] = true;
            return $this->changePass($args);
        }
        $data = $v->getData();

        $this->c->UserMapper->updateUser($user->id, ['password' => password_hash($data['password'], PASSWORD_DEFAULT), 'email_confirmed' => 1, 'activate_string' => null]);

        $this->iswev['s'][] = __('Pass updated');
        return $this->login(['_redirect' => $this->c->Router->link('Index')]);
    }
}
