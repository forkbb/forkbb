<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\User\User;
use function \ForkBB\__;

class Ban extends Page
{
    /**
     * Подготавливает данные для шаблона
     */
    public function ban(User $user): Page
    {
        $this->httpStatus = 403;
        $this->nameTpl    = 'ban';
#       $this->onlinePos  = 'ban';
#       $this->robots     = 'noindex';
        $this->titles     = __('Info');
        $this->ban        = $user->banInfo;
        $this->adminEmail = $this->c->config->o_admin_email;
        $this->bannedIp   = $user->isGuest;

        return $this;
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
    }
}
