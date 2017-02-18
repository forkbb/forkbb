<?php

namespace ForkBB\Models\Pages;

use R2\DependencyInjection\ContainerInterface;

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
        $this->c->get('Lang')->load('login');

        if ($this->c->get('Csrf')->check($args['token'], 'Logout', $args)) {
            $user = $this->c->get('user');

            $this->c->get('UserCookie')->deleteUserCookie();
            $this->c->get('Online')->delete($user);
            $this->c->get('UserMapper')->updateLastVisit($user);

            return $this->c->get('Redirect')->setPage('Index')->setMessage(__('Logout redirect'));
        }

        return $this->c->get('Redirect')->setPage('Index')->setMessage(__('Bad request'));
    }

    /**
     * Подготовка данных для страницы входа на форум
     * @param array $args
     * @return Page
     */
    public function login(array $args)
    {
        $this->c->get('Lang')->load('login');

        if (! isset($args['_username'])) {
            $args['_username'] = '';
        }
        if (! isset($args['_redirect'])) {
            $args['_redirect'] = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
            $args['_redirect'] = $this->c->get('Router')->validate($args['_redirect'], 'Index');
        }

        $this->titles = [
            __('Login'),
        ];
        $this->data = [
            'username' => $args['_username'],
            'formAction' => $this->c->get('Router')->link('Login'),
            'formToken' => $this->c->get('Csrf')->create('Login'),
            'forgetLink' => $this->c->get('Router')->link('Forget'),
            'regLink' => $this->config['o_regs_allow'] == '1'
                ? $this->c->get('Router')->link('Registration')
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
        $this->c->get('Lang')->load('login');

        $username = $this->c->get('Request')->postStr('username', '');
        $password = $this->c->get('Request')->postStr('password', '');
        $token = $this->c->get('Request')->postStr('token');
        $save = $this->c->get('Request')->postStr('save');

        $redirect = $this->c->get('Request')->postStr('redirect', '');
        $redirect = $this->c->get('Router')->validate($redirect, 'Index');

        $args = [
            '_username' => $username,
            '_redirect' => $redirect,
            '_save' => $save,
        ];

        if (! $this->c->get('Csrf')->check($token, 'Login')) {
            $this->iswev['e'][] = __('Bad token');
            return $this->login($args);
        }

        if (empty($username) || empty($password)) {
            $this->iswev['v'][] = __('Wrong user/pass');
            return $this->login($args);
        }

        if (! $this->loginProcess($username, $password, ! empty($save))) {
            $this->iswev['v'][] = __('Wrong user/pass');
            return $this->login($args);
        }

        return $this->c->get('Redirect')->setUrl($redirect)->setMessage(__('Login redirect'));
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
        $user = $this->c->get('UserMapper')->getUser($username, 'username');
        if (null == $user) {
            return false;
        }

        $authorized = false;
        $hash = $user->password;
        $update = [];

        // For FluxBB by Visman 1.5.10.74 and above
        if (strlen($hash) == 40) {
            if (hash_equals($hash, sha1($password . $this->c->getParameter('SALT1')))) {
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
            && $user->registrationIp != $this->c->get('user')->ip
        ) {
            $update['registration_ip'] = $this->c->get('user')->ip;
        }

        // изменения юзера в базе
        $this->c->get('UserMapper')->updateUser($user->id, $update);
        // обновления кэша
        if (isset($update['group_id'])) {
            $this->c->get('users_info update');
        }
        $this->c->get('Online')->delete($this->c->get('user'));
        $this->c->get('UserCookie')->setUserCookie($user->id, $hash, $save);

        return true;
    }

    /**
     * Подготовка данных для страницы восстановления пароля
     * @param array $args
     * @return Page
     */
    public function forget(array $args)
    {
        $this->c->get('Lang')->load('login');

        $this->nameTpl = 'login/forget';
        $this->onlinePos = 'forget';

        if (! isset($args['_email'])) {
            $args['_email'] = '';
        }

        $this->titles = [
            __('Request pass'),
        ];
        $this->data = [
            'email' => $args['_email'],
            'formAction' => $this->c->get('Router')->link('Forget'),
            'formToken' => $this->c->get('Csrf')->create('Forget'),
        ];

        return $this;
    }

    /**
     * Отправка письма для восстановления пароля
     * @return Page
     */
    public function forgetPost()
    {
        $this->c->get('Lang')->load('login');

        $token = $this->c->get('Request')->postStr('token');
        $email = $this->c->get('Request')->postStr('email');

        $args = [
            '_email' => $email,
        ];

        if (! $this->c->get('Csrf')->check($token, 'Forget')) {
            $this->iswev['e'][] = __('Bad token');
            return $this->forget($args);
        }

        $mail = $this->c->get('Mail');
        if (! $mail->valid($email)) {
            $this->iswev['v'][] = __('Invalid email');
            return $this->forget($args);
        }

        $user = $this->c->get('UserMapper')->getUser($email, 'email');
        if (null == $user) {
            $this->iswev['v'][] = __('Invalid email');
            return $this->forget($args);
        }

        if (! empty($user->lastEmailSent) && time() - $user->lastEmailSent < 3600) {
            $this->iswev['e'][] = __('Email flood', (int) (($user->lastEmailSent + 3600 - time()) / 60));
            return $this->forget($args);
        }

        $mail->setFolder($this->c->getParameter('DIR_LANG'))
            ->setLanguage($user->language);

        $key = 'p' . $this->c->get('Secury')->randomPass(75);
        $link = $this->c->get('Router')->link('ChangePassword', ['email' => $email, 'key' => $key]);
        $data = ['key' => $key, 'link' => $link];

        if ($mail->send($email, 'change_password.tpl', $data)) {
            $this->c->get('UserMapper')->updateUser($user->id, ['activate_string' => $key, 'last_email_sent' => time()]);
            return $this->c->get('Message')->message(__('Forget mail', $this->config['o_admin_email']), false, 200);
        } else {
            return $this->c->get('Message')->message(__('Error mail', $this->config['o_admin_email']), true, 200);
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

        // что-то пошло не так
        if (! $this->c->get('Mail')->valid($args['email'])
            || ($user = $this->c->get('UserMapper')->getUser($args['email'], 'email')) === null
            || empty($user->activateString)
            || $user->activateString{0} !== 'p'
            || ! hash_equals($user->activateString, $args['key'])
        ) {
            return $this->c->get('Message')->message(__('Bad request'), false);
        }

        $this->c->get('Lang')->load('login');
        $this->c->get('Lang')->load('profile');

        $this->titles = [
            __('Change pass'),
        ];
        $this->data = [
            'formAction' => $this->c->get('Router')->link('ChangePassword', $args),
            'formToken' => $this->c->get('Csrf')->create('ChangePassword', $args),
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
        $token = $this->c->get('Request')->postStr('token');
        $password = $this->c->get('Request')->postStr('password', '');
        $password2 = $this->c->get('Request')->postStr('password2', '');

        $this->c->get('Lang')->load('login');

        // что-то пошло не так
        if (! $this->c->get('Mail')->valid($args['email'])
            || ($user = $this->c->get('UserMapper')->getUser($args['email'], 'email')) === null
            || empty($user->activateString)
            || $user->activateString{0} !== 'p'
            || ! hash_equals($user->activateString, $args['key'])
        ) {
            return $this->c->get('Message')->message(__('Bad request'), false);
        }

        if (! $this->c->get('Csrf')->check($token, 'ChangePassword', $args)) {
            $this->iswev['e'][] = __('Bad token');
            return $this->changePass($args);
        }
        if (mb_strlen($password) < 6) {
            $this->iswev['v'][] = __('Pass too short');
            return $this->changePass($args);
        }
        if ($password !== $password2) {
            $this->iswev['v'][] = __('Pass not match');
            return $this->changePass($args);
        }

        $this->c->get('UserMapper')->updateUser($user->id, ['password' => password_hash($password, PASSWORD_DEFAULT), 'activate_string' => null]);

        $this->c->get('Lang')->load('profile');
        $this->iswev['s'][] = __('Pass updated');
        return $this->login([]);
    }
}
