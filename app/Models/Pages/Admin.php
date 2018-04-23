<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;
use ForkBB\Models\Page;

class Admin extends Page
{
    /**
     * @var array
     */
    protected $aCrumbs = [];

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->aIndex    = 'index'; # string Указатель на активный пункт навигации в меню админки
        $this->fIndex    = 'admin';
        $this->onlinePos = 'admin';
        $this->robots    = 'noindex, nofollow';

        $container->Lang->load('admin');
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare()
    {
        $this->aNavigation = $this->aNavigation();
        $this->crumbs      = $this->crumbs(...$this->aCrumbs);

        parent::prepare();
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации админки
     *
     * @return array
     */
    protected function aNavigation()
    {
        $r   = $this->c->Router;
        $nav = [
            'index' => [$r->link('Admin'), \ForkBB\__('Admin index')],
            'users' => [$r->link('AdminUsers'), \ForkBB\__('Users')],
        ];

        if ($this->user->isAdmin || $this->user->g_mod_ban_users == '1') {
            $nav['bans'] = ['admin_bans.php', \ForkBB\__('Bans')];
        }
        if ($this->user->isAdmin || $this->c->config->o_report_method == '0' || $this->c->config->o_report_method == '2') {
            $nav['reports'] = ['admin_reports.php', \ForkBB\__('Reports')];
        }

        if ($this->user->isAdmin) {
            $nav += [
                'options'     => [$r->link('AdminOptions'), \ForkBB\__('Admin options')],
                'permissions' => [$r->link('AdminPermissions'), \ForkBB\__('Permissions')],
                'categories'  => [$r->link('AdminCategories'), \ForkBB\__('Categories')],
                'forums'      => [$r->link('AdminForums'), \ForkBB\__('Forums')],
                'groups'      => [$r->link('AdminGroups'), \ForkBB\__('User groups')],
                'censoring'   => [$r->link('AdminCensoring'), \ForkBB\__('Censoring')],
                'maintenance' => [$r->link('AdminMaintenance'), \ForkBB\__('Maintenance')]
            ];
        }

        return $nav;
    }

    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     *
     * @param mixed $crumbs
     *
     * @return array
     */
    protected function crumbs(...$crumbs)
    {
        if ('index' !== $this->aIndex) {
            if (isset($this->aNavigation[$this->aIndex])) {
                $crumbs[] = $this->aNavigation[$this->aIndex];
            } else {
                $crumbs[] = 'unknown';
            }
        }

        $crumbs[] = [$this->c->Router->link('Admin'), \ForkBB\__('Admin title')];

        return parent::crumbs(...$crumbs);
    }
}
