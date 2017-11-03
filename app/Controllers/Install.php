<?php

namespace ForkBB\Controllers;

use ForkBB\Core\Container;
use ForkBB\Models\User;

class Install
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Маршрутиризация
     *
     * @return Page
     */
    public function routing()
    {
        $uri = $_SERVER['REQUEST_URI'];
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $this->c->BASE_URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://'
            . preg_replace('%:(80|443)$%', '', $_SERVER['HTTP_HOST'])
            . substr($uri, 0, (int) strrpos($uri, '/'));

        $this->c->Lang->load('common', $this->c->config->o_default_lang);
        $this->c->user = new User(['id' => 2, 'group_id' => $this->c->GROUP_ADMIN], $this->c);

        $r = $this->c->Router;
        $r->add('GET', '/install', 'Install:install', 'Install');
        $r->add('POST', '/install', 'Install:installPost');

        $route = $r->route($_SERVER['REQUEST_METHOD'], $uri);
        $page = null;
        switch ($route[0]) {
            case $r::OK:
                // ... 200 OK
                list($page, $action) = explode(':', $route[1], 2);
                $page = $this->c->$page->$action($route[2]);
                break;
            default:
                $page = $this->c->Redirect->page('Install')->message('Redirect to install');
                break;
        }
        return $page;
    }
}
