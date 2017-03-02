<?php

namespace ForkBB\Controllers;

use ForkBB\Core\Container;

class Routing
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Конструктор
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Маршрутиризация
     * @return Page
     */
    public function routing()
    {
        $user = $this->c->user;
        $config = $this->c->config;
        $r = $this->c->Router;

        // регистрация/вход/выход
        if ($user->isGuest) {
            // вход
            $r->add('GET', '/login', 'Auth:login', 'Login');
            $r->add('POST', '/login', 'Auth:loginPost');
            // забыли пароль
            $r->add('GET', '/login/forget', 'Auth:forget', 'Forget');
            $r->add('POST', '/login/forget', 'Auth:forgetPost');
            // смена пароля
            $r->add('GET', '/login/{email}/{key}/{hash}', 'Auth:changePass', 'ChangePassword');
            $r->add('POST', '/login/{email}/{key}/{hash}', 'Auth:changePassPost');

            // регистрация
            if ($config['o_regs_allow'] == '1') {
                $r->add('GET', '/registration', 'Rules:confirmation', 'Register');
                $r->add('POST', '/registration/agree', 'Register:reg', 'RegisterForm');
                $r->add('GET', '/registration/activate/{id:\d+}/{key}/{hash}', 'Register:activate', 'RegActivate');
            }
        } else {
            // выход
            $r->add('GET', '/logout/{token}', 'Auth:logout', 'Logout');

            // обработка "кривых" перенаправлений с логина и регистрации
            $r->add('GET', '/login[/{tail:.*}]', 'Redirect:toIndex');
            $r->add('GET', '/registration[/{tail:.*}]', 'Redirect:toIndex');
        }
        // просмотр разрешен
        if ($user->gReadBoard == '1') {
            // главная
            $r->add('GET', '/', 'Index:view', 'Index');
            // правила
            if ($config['o_rules'] == '1' && (! $user->isGuest || $config['o_regs_allow'] == '1')) {
                $r->add('GET', '/rules', 'Rules:view', 'Rules');
            }
            // поиск
            if ($user->gSearch == '1') {
                $r->add('GET', '/search', 'Search:view', 'Search');
            }
            // юзеры
            if ($user->gViewUsers == '1') {
                // список пользователей
                $r->add('GET', '/userlist[/page/{page}]', 'Userlist:view', 'Userlist');
                // юзеры
                $r->add('GET', '/user/{id:\d+}[/{name}]', 'Profile:view', 'User'); //????
            }

            // разделы
            $r->add('GET', '/forum/{id:\d+}[/{name}][/page/{page:\d+}]', 'Forum:view', 'Forum');
            // темы
            $r->add('GET', '/post/{id:\d+}#p{id}', 'Topic:viewpost', 'viewPost');

        }
        // админ и модератор
        if ($user->isAdmMod) {
            $r->add('GET', '/admin/', 'AdminIndex:index', 'Admin');
            $r->add('GET', '/admin/statistics', 'AdminStatistics:statistics', 'AdminStatistics');
        }
        // только админ
        if ($user->isAdmin) {
            $r->add('GET', '/admin/statistics/info', 'AdminStatistics:info', 'AdminInfo');
        }

        $uri = $_SERVER['REQUEST_URI'];
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $route = $r->route($_SERVER['REQUEST_METHOD'], $uri);
        $page = null;
        switch ($route[0]) {
            case $r::OK:
                // ... 200 OK
                list($page, $action) = explode(':', $route[1], 2);
                $page = $this->c->$page->$action($route[2]);
                break;
            case $r::NOT_FOUND:
                // ... 404 Not Found
                if ($user->gReadBoard != '1' && $user->isGuest) {
                    $page = $this->c->Redirect->setPage('Login');
                } else {
                    $page = $this->c->Message->message('Bad request');
                }
                break;
            case $r::METHOD_NOT_ALLOWED:
                // ... 405 Method Not Allowed
                $page = $this->c->Message->message('Bad request', true, 405, ['Allow: ' . implode(',', $route[1])]);
                break;
            case $r::NOT_IMPLEMENTED:
                // ... 501 Not implemented
                $page = $this->c->Message->message('Bad request', true, 501);
                break;
        }
        return $page;
    }
}
