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
        $user = $this->c->get('user');

        $this->c->get('Lang')->load('login');

        if ($this->c->get('Csrf')->check($args['token'], 'Logout', $args)) {
            $user->logout();
            return $this->c->get('Redirect')->setPage('Index')->setMessage(__('Logout redirect'));
        }

        return $this->c->get('Redirect')->setPage('Index')->setMessage(__('Bad request'));
    }

    /**
     * Подготовка данных для страницы входа на форум
     * @param array $args
     * @return Page
     */
    public function login($args)
    {
        $this->c->get('Lang')->load('login');

        if (! isset($args['_name'])) {
            $args['_name'] = '';
        }
        if (! isset($args['_redirect'])) {
            $args['_redirect'] = empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
            $args['_redirect'] = $this->c->get('Router')->validate($args['_redirect'], 'Index');
        }

        $this->titles = [
            __('Login'),
        ];
        $this->data = [
            'name' => $args['_name'],
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

        $name = $this->c->get('Request')->postStr('name', '');
        $password = $this->c->get('Request')->postStr('password', '');
        $token = $this->c->get('Request')->postStr('token');
        $save = $this->c->get('Request')->postStr('save');

        $redirect = $this->c->get('Request')->postStr('redirect', '');
        $redirect = $this->c->get('Router')->validate($redirect, 'Index');

        $args = [
            '_name' => $name,
            '_redirect' => $redirect,
            '_save' => $save,
        ];

        if (! $this->c->get('Csrf')->check($token, 'Login')) {
            $this->iswev['e'][] = __('Bad token');
            return $this->login($args);
        }

        if (empty($name) || empty($password)) {
            $this->iswev['v'][] = __('Wrong user/pass');
            return $this->login($args);
        }

        $result = $this->c->get('user')->login($name, $password, ! empty($save));

        if (false === $result) {
            $this->iswev['v'][] = __('Wrong user/pass');
            return $this->login($args);
        }

        return $this->c->get('Redirect')->setUrl($redirect)->setMessage(__('Login redirect'));
    }

    /**
     * Подготовка данных для страницы восстановления пароля
     * @param array $args
     * @return Page
     */
    public function forget($args)
    {
        $this->c->get('Lang')->load('login');

        $this->nameTpl = 'forget';
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



    }
}
