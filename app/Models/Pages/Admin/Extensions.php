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
use Throwable;
use function \ForkBB\__;

class Extensions extends Admin
{
    /**
     * Подготавливает данные для шаблона
     */
    public function info(): Page
    {
        $this->c->Lang->load('admin_extensions');

        $this->nameTpl    = 'admin/extensions';
        $this->aIndex     = 'extensions';
        $this->extensions = $this->c->extensions->repository;

        return $this;
    }
}
