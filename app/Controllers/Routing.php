<?php

namespace ForkBB\Controllers;

use R2\DependencyInjection\ContainerInterface;

class Routing
{
    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    /**
     * Конструктор
     * @param array $config
     */
    public function __construct(ContainerInterface $container)
    {
        $this->c = $container;
    }

    /**
     * Маршрутиризация
     * @return Page
     */
    public function routing()
    {
        $user = $this->c->get('user');
        $config = $this->c->get('config');
        $r = $this->c->get('Router');

        // регистрация/вход/выход
        if ($user['is_guest']) {
            // вход
            $r->add('GET', '/login', 'Auth:login', 'Login');
            $r->add('POST', '/login', 'Auth:loginPost');
            $r->add('GET', '/login/forget', 'Auth:forget', 'Forget');
            // регистрация
            if ($config['o_regs_allow'] == '1') {
                $r->add('GET', '/registration', 'Registration:reg', 'Registration'); //????
            }
        } else {
            // выход
            $r->add('GET', '/logout/{token}', 'Auth:logout', 'Logout');
        }
        // просмотр разрешен
        if ($user['g_read_board'] == '1') {
            // главная
            $r->add('GET', '/', 'Index:view', 'Index');
            // правила
            if ($config['o_rules'] == '1' && (! $user['is_guest'] || $config['o_regs_allow'] == '1')) {
                $r->add('GET', '/rules', 'Rules:view', 'Rules');
            }
            // поиск
            if ($user['g_search'] == '1') {
                $r->add('GET', '/search', 'Search:view', 'Search');
            }
            // юзеры
            if ($user['g_view_users'] == '1') {
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
        if ($user['is_admmod']) {
            $r->add('GET', '/admin/', 'AdminIndex:index', 'Admin');
            $r->add('GET', '/admin/statistics', 'AdminStatistics:statistics', 'AdminStatistics');
        }
        // только админ
        if ($user['g_id'] == PUN_ADMIN) {
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
                $page = $this->c->get($page)->$action($route[2]);
                break;
            case $r::NOT_FOUND:
                // ... 404 Not Found
                if ($user['g_read_board'] != '1' && $user['is_guest']) {
                    $page = $this->c->get('Redirect')->setPage('Login');
                } else {
//                  $page = $this->c->get('Message')->message('Bad request');
                }
                break;
            case $r::METHOD_NOT_ALLOWED:
                // ... 405 Method Not Allowed
                $page = $this->c->get('Message')->message('Bad request', true, 405, ['Allow: ' . implode(',', $route[1])]);
                break;
            case $r::NOT_IMPLEMENTED:
                // ... 501 Not implemented
                $page = $this->c->get('Message')->message('Bad request', true, 501);
                break;
        }
        return $page;
    }

}
