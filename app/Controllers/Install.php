<?php

namespace ForkBB\Controllers;

use ForkBB\Core\Container;
use ForkBB\Models\Page;

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
    public function routing(): Page
    {
        $uri = $_SERVER['REQUEST_URI'];
        if (false !== ($pos = \strpos($uri, '?'))) {
            $uri = \substr($uri, 0, $pos);
        }
        $uri = \rawurldecode($uri);

        $this->c->Lang->load('common', $this->c->config->o_default_lang);
        $this->c->user = $this->c->users->create(['id' => 2, 'group_id' => $this->c->GROUP_ADMIN]);

        $r = $this->c->Router;
        $r->add(
            $r::DUO,
            '/install',
            'Install:install',
            'Install'
        );

        $method = $_SERVER['REQUEST_METHOD'];

        $route = $r->route($method, $uri);
        $page  = null;
        switch ($route[0]) {
            case $r::OK:
                // ... 200 OK
                list($page, $action) = \explode(':', $route[1], 2);
                $page = $this->c->$page->$action($route[2], $method);
                break;
            default:
                $page = $this->c->Redirect->page('Install')->message('Redirect to install');
                break;
        }

        return $page;
    }
}
