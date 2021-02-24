<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;
use ForkBB\Models\Page;
use function \ForkBB\__;

class Admin extends Page
{
    /**
     * @var array
     */
    protected $aCrumbs = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->aIndex    = 'index'; # string Указатель на активный пункт навигации в меню админки
        $this->fIndex    = 'admin';
        $this->onlinePos = 'admin';
        $this->robots    = 'noindex, nofollow';
        $this->hhsLevel  = 'secure';

        $container->Lang->load('admin');

        $this->pageHeader('adminStyle', 'link', 9000, [
            'rel'  => 'stylesheet',
            'type' => 'text/css',
            'href' => $this->publicLink("/style/{$this->user->style}/admin.css"),
        ]);
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
        $this->aNavigation = $this->aNavigation();
        $this->crumbs      = $this->crumbs(...$this->aCrumbs);

        parent::prepare();
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации админки
     */
    protected function aNavigation(): array
    {
        $r   = $this->c->Router;
        $nav = [
            'index' => [$r->link('Admin'), 'Admin index'],
            'users' => [$r->link('AdminUsers'), 'Users'],
        ];

        if ($this->c->userRules->banUsers) {
            $nav['bans'] = [$r->link('AdminBans'), 'Bans'];
        }
        if (
            $this->user->isAdmin
            || 0 === $this->c->config->i_report_method
            || 2 === $this->c->config->i_report_method
        ) {
            $nav['reports'] = [$r->link('AdminReports'), 'Reports'];
        }

        if ($this->user->isAdmin) {
            $nav += [
                'options'     => [$r->link('AdminOptions'), 'Admin options'],
                'parser'      => [$r->link('AdminParser'), 'Parser settings'],
                'categories'  => [$r->link('AdminCategories'), 'Categories'],
                'forums'      => [$r->link('AdminForums'), 'Forums'],
                'groups'      => [$r->link('AdminGroups'), 'User groups'],
                'censoring'   => [$r->link('AdminCensoring'), 'Censoring'],
                'logs'        => [$r->link('AdminLogs'), 'Logs'],
                'maintenance' => [$r->link('AdminMaintenance'), 'Maintenance'],
            ];
        }

        return $nav;
    }

    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     */
    protected function crumbs(/* mixed */ ...$crumbs): array
    {
        if ('index' !== $this->aIndex) {
            if (isset($this->aNavigation[$this->aIndex])) {
                $crumbs[] = [
                    $this->aNavigation[$this->aIndex][0],
                    __($this->aNavigation[$this->aIndex][1]),
                ];
            } else {
                $crumbs[] = 'unknown';
            }
        }

        $crumbs[] = [$this->c->Router->link('Admin'), __('Admin title')];

        return parent::crumbs(...$crumbs);
    }
}
