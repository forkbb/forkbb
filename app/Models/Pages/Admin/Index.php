<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;

class Index extends Admin
{
    /**
     * Подготавливает данные для шаблона
     */
    public function index(): Page
    {
        $this->c->Lang->load('admin_index');

        $this->nameTpl  = 'admin/index';
        $this->revision = $this->c->config->i_fork_revision;
        $this->linkStat = $this->c->Router->link('AdminStatistics');

        return $this;
    }
}
