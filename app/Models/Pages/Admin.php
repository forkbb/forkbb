<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;
use ForkBB\Models\Page;

class Admin extends Page
{
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
        parent::prepare();
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации админки
     * 
     * @return array
     */
    protected function aNavigation()
    {
        $user = $this->c->user;
        $r = $this->c->Router;

        $nav = [
            'Moderator menu'  => [
                'index' => [$r->link('Admin'), \ForkBB\__('Admin index')],
                'users' => ['admin_users.php', \ForkBB\__('Users')],
            ],
        ];
        if ($user->isAdmin || $user->g_mod_ban_users == '1') {
            $nav['Moderator menu']['bans'] = ['admin_bans.php', \ForkBB\__('Bans')];
        }
        if ($user->isAdmin || $this->c->config->o_report_method == '0' || $this->c->config->o_report_method == '2') {
            $nav['Moderator menu']['reports'] = ['admin_reports.php', \ForkBB\__('Reports')];
        }

        if ($user->isAdmin) {
            $nav['Admin menu'] = [
                'options'     => [$r->link('AdminOptions'), \ForkBB\__('Admin options')],
                'permissions' => ['admin_permissions.php', \ForkBB\__('Permissions')],
                'categories'  => ['admin_categories.php', \ForkBB\__('Categories')],
                'forums'      => ['admin_forums.php', \ForkBB\__('Forums')],
                'groups'      => [$r->link('AdminGroups'), \ForkBB\__('User groups')],
                'censoring'   => ['admin_censoring.php', \ForkBB\__('Censoring')],
                'maintenance' => ['admin_maintenance.php', \ForkBB\__('Maintenance')]
            ];
        }

        return $nav;
    }

    /**
     * Возвращает title страницы
     * $this->pageTitle
     * 
     * @param array $titles
     * 
     * @return string
     */
    protected function getPageTitle(array $titles = [])
    {
        if (empty($titles)) {
            $titles = $this->titles;
        }
        $titles[] = \ForkBB\__('Admin title');
        return parent::getPageTitle($titles);
    }
}
