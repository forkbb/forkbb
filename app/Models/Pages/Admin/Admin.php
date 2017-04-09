<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Pages\Page;

abstract class Admin extends Page
{
    /**
     * Указатель на активный пункт навигации админки
     * @var string
     */
    protected $adminIndex;

    /**
     * Указатель на активный пункт навигации
     * @var string
     */
    protected $index = 'admin';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var string
     */
    protected $onlinePos = 'admin';

    /**
     * Переменная для meta name="robots"
     * @var string
     */
    protected $robots = 'noindex, nofollow';

    /**
     * Конструктор
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $container->Lang->load('admin');
    }

    /**
     * Возвращает данные для шаблона
     * @return array
     */
    public function getData()
    {
        $data = parent::getData();
        $data['aNavigation'] = $this->aNavigation();
        $data['aIndex'] = $this->adminIndex;
        return $data;
    }

    /**
     * Формирует title страницы
     * @param array $titles
     * @return string
     */
    protected function pageTitle(array $titles = [])
    {
        $titles = $this->titles;
        $titles[] = __('Admin title');
        return parent::pageTitle($titles);
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации админки
     * @return array
     */
    protected function aNavigation()
    {
        $user = $this->c->user;
        $r = $this->c->Router;

        $nav = [
            'Moderator menu'  => [
                'index' => [$r->link('Admin'), __('Admin index')],
                'users' => ['admin_users.php', __('Users')],
            ],
        ];
        if ($user->isAdmin || $user->gModBanUsers == '1') {
            $nav['Moderator menu']['bans'] = ['admin_bans.php', __('Bans')];
        }
        if ($user->isAdmin || $this->config['o_report_method'] == '0' || $this->config['o_report_method'] == '2') {
            $nav['Moderator menu']['reports'] = ['admin_reports.php', __('Reports')];
        }

        if ($user->isAdmin) {
            $nav['Admin menu'] = [
                'options' => ['admin_options.php', __('Admin options')],
                'permissions' => ['admin_permissions.php', __('Permissions')],
                'categories' => ['admin_categories.php', __('Categories')],
                'forums' => ['admin_forums.php', __('Forums')],
                'groups' => [$r->link('AdminGroups'), __('User groups')],
                'censoring' => ['admin_censoring.php', __('Censoring')],
                'maintenance' => ['admin_maintenance.php', __('Maintenance')]
            ];
        }

        return $nav;
    }

}
