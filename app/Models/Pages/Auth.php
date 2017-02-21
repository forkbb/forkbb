<?php

namespace ForkBB\Models\Pages;

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
     * Выход пользователя
     * @param array $args
     * @retrun Page
     */
    public function logout($args)
    {
        $this->c->Lang->load('login');

        if ($this->c->Csrf->verify($args['token'], 'Logout', $args)) {
            $user = $this->c->user;

            $this->c->UserCookie->deleteUserCookie();
            $this->c->Online->delete($user);
            $this->c->UserMapper->updateLastVisit($user);

            return $this->c->Redirect->setPage('Index')->setMessage(__('Logout redirect'));
        }

        return $this->c->Redirect->setPage('Index')->setMessage(__('Bad token'));
    }

    /**
     * Подготовка данных для страницы входа на форум
     * @param array $args
     * @return Page
     */
    public function login(array $args)
    {
        $this->c->Lang->load('login');

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
            'username' => $args['_username'],
            'formAction' => $this->c->Router->link('Login'),
            'formToken' => $this->c->Csrf->create('Login'),
            'forgetLink' => $this->c->Router->link('Forget'),
            'regLink' => $this->config['o_regs_allow'] == '1'
                ? $this->c->Router->link('Registration')
                : null,
            'formRedirect' => $args['_redirect'],
            'formSave' => ! empty($args['_save'])
        ];

        return $this;
    }

    /**
     * Вход на форум
     * @return Page
     */
    public function loginPost()
    {
        $this->c->Lang->load('login');

        $v = $this->c->Validator;
        $v->setRules([
            'token'    => 'token:Login',
            'redirect' => 'referer:Index',
            'username' => ['required|string|min:2|max:25', __('Username')],
            'password' => ['required|string', __('Password')],
            'save'     => 'checkbox',
        ]);

        $ok = $v->validation($_POST);
        $data = $v->getData();
        $this->iswev = $v->getErrors();

        if ($ok && ! $this->loginProcess($data['username'], $data['password'], $data['save'])) {
            $this->iswev['v'][] = __('Wrong user/pass');
            $ok = false;
        }

        if ($ok) {
            return $this->c->Redirect->setUrl($data['redirect'])->setMessage(__('Login redirect'));
        } else {
            return $this->login([
                '_username' => $data['username'],
                '_redirect' => $data['redirect'],
                '_save'     => $data['save'],
            ]);
        }
    }

    /**
     * Вход на форум
     * @param string $username
     * @param string $password
     * @param bool $save
     * @return bool
     */
    protected function loginProcess($username, $password, $save)
    {
        $user = $this->c->UserMapper->getUser($username, 'username');
        if (null == $user) {
            return false;
        }

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

        if (! $authorized) {
            return false;
        }

        // Update the status if this is the first time the user logged in
        if ($user->isUnverified) {
            $update['group_id'] = (int) $this->config['o_default_user_group'];
        }

        // перезаписываем ip админа и модератора - Visman
        if ($user->isAdmMod
            && $this->config['o_check_ip']
            && $user->registrationIp != $this->c->user->ip
        ) {
            $update['registration_ip'] = $this->c->user->ip;
        }

        // изменения юзера в базе
        $this->c->UserMapper->updateUser($user->id, $update);
        // обновления кэша
        if (isset($update['group_id'])) {
            $this->c->{'users_info update'};
        }
        $this->c->Online->delete($this->c->user);
        $this->c->UserCookie->setUserCookie($user->id, $hash, $save);

        return true;
    }

    /**
     * Подготовка данных для страницы восстановления пароля
     * @param array $args
     * @return Page
     */
    public function forget(array $args)
    {
        $this->c->Lang->load('login');

        $this->nameTpl = 'login/forget';
        $this->onlinePos = 'forget';

        if (! isset($args['_email'])) {
            $args['_email'] = '';
        }

        $this->titles = [
            __('Password reset'),
        ];
        $this->data = [
            'email' => $args['_email'],
            'formAction' => $this->c->Router->link('Forget'),
            'formToken' => $this->c->Csrf->create('Forget'),
        ];

        return $this;
    }

    /**
     * Отправка письма для восстановления пароля
     * @return Page
     */
    public function forgetPost()
    {
        $this->c->Lang->load('login');

        $v = $this->c->Validator;
        $v->setRules([
            'token' => 'token:Forget',
            'email' => 'required|email',
        ])->setMessages([
            'email' => __('Invalid email'),
        ]);

        $ok = $v->validation($_POST);
        $data = $v->getData();
        $this->iswev = $v->getErrors();

        if ($ok && ($user = $this->c->UserMapper->getUser($data['email'], 'email')) === null) {
            $this->iswev['v'][] = __('Invalid email');
            $ok = false;
        }
        if ($ok && ! empty($user->lastEmailSent) && time() - $user->lastEmailSent < 3600) {
            $this->iswev['e'][] = __('Email flood', (int) (($user->lastEmailSent + 3600 - time()) / 60));
            $ok = false;
        }

        if (! $ok) {
            return $this->forget([
                '_email' => $data['email'],
            ]);
        }

        $mail = $this->c->Mail;
        $mail->setFolder($this->c->DIR_LANG)
            ->setLanguage($user->language);

        $key = 'p' . $this->c->Secury->randomPass(75);
        $hash = $this->c->Secury->hash($data['email'] . $key);
        $link = $this->c->Router->link('ChangePassword', ['email' => $data['email'], 'key' => $key, 'hash' => $hash]);
        $tplData = ['link' => $link];

        if ($mail->send($data['email'], 'change_password.tpl', $tplData)) {
            $this->c->UserMapper->updateUser($user->id, ['activate_string' => $key, 'last_email_sent' => time()]);
            return $this->c->Message->message(__('Forget mail', $this->config['o_admin_email']), false, 200);
        } else {
            return $this->c->Message->message(__('Error mail', $this->config['o_admin_email']), true, 200);
        }
    }

    /**
     * Подготовка данных для формы изменения пароля
     * @param array $args
     * @return Page
     */
    public function changePass(array $args)
    {
        $this->nameTpl = 'login/password';
        $this->onlinePos = 'password';

        if (isset($args['_ok'])) {
            unset($args['_ok']);
        } else {
            // что-то пошло не так
            if (! hash_equals($args['hash'], $this->c->Secury->hash($args['email'] . $args['key']))
                || ! $this->c->Mail->valid($args['email'])
                || ($user = $this->c->UserMapper->getUser($args['email'], 'email')) === null
                || empty($user->activateString)
                || $user->activateString{0} !== 'p'
                || ! hash_equals($user->activateString, $args['key'])
            ) {
                return $this->c->Message->message(__('Bad request'), false);
            }
        }

        $this->c->Lang->load('login');
        $this->c->Lang->load('profile');

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
        $this->c->Lang->load('login');

        // что-то пошло не так
        if (! hash_equals($args['hash'], $this->c->Secury->hash($args['email'] . $args['key']))
            || ! $this->c->Mail->valid($args['email'])
            || ($user = $this->c->UserMapper->getUser($args['email'], 'email')) === null
            || empty($user->activateString)
            || $user->activateString{0} !== 'p'
            || ! hash_equals($user->activateString, $args['key'])
        ) {
            return $this->c->Message->message(__('Bad request'), false);
        }

        $this->c->Lang->load('profile');

        $v = $this->c->Validator;
        $v->setRules([
            'token'     => 'token:ChangePassword',
            'password'  => ['required|string|min:8', __('New pass')],
            'password2' => 'required|same:password',
        ])->setArguments([
            'token' => $args,
        ])->setMessages([
            'password2' => __('Pass not match'),
        ]);

        if (! $v->validation($_POST)) {
            $this->iswev = $v->getErrors();
            $args['_ok'] = true;
            return $this->changePass($args);
        }
        $data = $v->getData();

        $this->c->UserMapper->updateUser($user->id, ['password' => password_hash($data['password'], PASSWORD_DEFAULT), 'activate_string' => null]);

        $this->iswev['s'][] = __('Pass updated');
        return $this->login(['_redirect' => $this->c->Router->link('Index')]);
    }
}
