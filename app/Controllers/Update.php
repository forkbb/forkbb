<?php

namespace ForkBB\Controllers;

use ForkBB\Core\Container;
use ForkBB\Models\Page;

class Update
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Маршрутиризация
     */
    public function routing(): Page
    {
        $uri = $_SERVER['REQUEST_URI'];
        if (false !== ($pos = \strpos($uri, '?'))) {
            $uri = \substr($uri, 0, $pos);
        }
        $uri = \rawurldecode($uri);

        $this->c->user = $this->c->users->create(['id' => 2, 'group_id' => $this->c->GROUP_ADMIN]); //???? id?
        $this->c->Lang->load('common');

        $r = $this->c->Router;
        $r->add(
            $r::GET,
            '/admin/update/{uid}/{stage:\d+}[/{start:\d+}]',
            'AdminUpdate:stage',
            'AdminUpdateStage'
        );
        $r->add(
            $r::DUO,
            '/admin/update',
            'AdminUpdate:view',
            'AdminUpdate'
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
                $page = $this->c->AdminUpdate->view([], 'GET');
                break;
        }

        return $page;
    }
}
