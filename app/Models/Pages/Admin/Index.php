<?php

namespace ForkBB\Models\Pages\Admin;

class Index extends Admin
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'admin/index';

    /**
     * Указатель на активный пункт навигации админки
     * @var string
     */
    protected $adminIndex = 'index';

    /**
     * Подготавливает данные для шаблона
     * @return Page
     */
    public function index()
    {
        $this->c->get('Lang')->load('admin_index');
        $this->data = [
            'version' => $this->config['s_fork_version'] . '.' . $this->config['i_fork_revision'],
            'linkStat' => $this->c->get('Router')->link('AdminStatistics'),
        ];
        $this->titles[] = __('Admin index');
        return $this;
    }
}
