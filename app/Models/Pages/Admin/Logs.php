<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;

class Logs extends Admin
{
    /**
     * Подготавливает данные для шаблона
     */
    public function info(): Page
    {
        $this->c->Lang->load('admin_logs');

        $this->nameTpl  = 'admin/logs';
        $this->aIndex   = 'logs';
        $logsFiles      = $this->c->LogViewer->files();
        $this->logsInfo = $this->c->LogViewer->info($logsFiles);

        return $this;
    }
}
