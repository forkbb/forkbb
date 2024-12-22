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

abstract class Admin extends Page
{
    protected array $aCrumbs = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->identifier   = 'admin';
        $this->aIndex       = 'index'; # string Указатель на активный пункт навигации в меню админки
        $this->fIndex       = self::FI_ADMIN;
        $this->onlinePos    = 'admin';
        $this->onlineDetail = null;
        $this->robots       = 'noindex, nofollow';
        $this->hhsLevel     = 'secure';

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

        if ($this->userRules->banUsers) {
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
                'uploads'     => [$r->link('AdminUploads'), 'Uploads'],
                'antispam'    => [$r->link('AdminAntispam'), 'Antispam'],
                'logs'        => [$r->link('AdminLogs'), 'Logs'],
                'extensions'  => [$r->link('AdminExtensions'), 'Extensions'],
                'maintenance' => [$r->link('AdminMaintenance'), 'Maintenance'],
            ];
        }

        return $nav;
    }

    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     */
    protected function crumbs(mixed ...$crumbs): array
    {
        if ('index' !== $this->aIndex) {
            if (isset($this->aNavigation[$this->aIndex])) {
                $crumbs[] = $this->aNavigation[$this->aIndex];

            } else {
                $crumbs[] = [null, ['%s', 'unknown']];
            }
        }

        $crumbs[] = [$this->c->Router->link('Admin'), 'Admin title', null, 'admin'];
        $result   = parent::crumbs(...$crumbs);

        $this->adminHeader = \end($result)[1];

        return $result;
    }
}
