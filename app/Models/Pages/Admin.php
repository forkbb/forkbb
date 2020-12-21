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

        $container->Lang->load('admin');
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
            'index' => [$r->link('Admin'), __('Admin index')],
            'users' => [$r->link('AdminUsers'), __('Users')],
        ];

        if ($this->c->userRules->banUsers) {
            $nav['bans'] = [$r->link('AdminBans'), __('Bans')];
        }
        if (
            $this->user->isAdmin
            || 0 === $this->c->config->i_report_method
            || 2 === $this->c->config->i_report_method
        ) {
            $nav['reports'] = [$r->link('AdminReports'), __('Reports')];
        }

        if ($this->user->isAdmin) {
            $nav += [
                'options'     => [$r->link('AdminOptions'), __('Admin options')],
                'parser'      => [$r->link('AdminParser'), __('Parser settings')],
                'categories'  => [$r->link('AdminCategories'), __('Categories')],
                'forums'      => [$r->link('AdminForums'), __('Forums')],
                'groups'      => [$r->link('AdminGroups'), __('User groups')],
                'censoring'   => [$r->link('AdminCensoring'), __('Censoring')],
                'maintenance' => [$r->link('AdminMaintenance'), __('Maintenance')]
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
                $crumbs[] = $this->aNavigation[$this->aIndex];
            } else {
                $crumbs[] = 'unknown';
            }
        }

        $crumbs[] = [$this->c->Router->link('Admin'), __('Admin title')];

        return parent::crumbs(...$crumbs);
    }
}
