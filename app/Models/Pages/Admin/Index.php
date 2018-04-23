<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Pages\Admin;

class Index extends Admin
{
    /**
     * Подготавливает данные для шаблона
     *
     * @return Page
     */
    public function index()
    {
        $this->c->Lang->load('admin_index');

        $this->nameTpl  = 'admin/index';
        $this->revision = $this->c->config->i_fork_revision;
        $this->linkStat = $this->c->Router->link('AdminStatistics');

        return $this;
    }
}
