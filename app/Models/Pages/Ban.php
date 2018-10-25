<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;

class Ban extends Page
{
    /**
     * Подготавливает данные для шаблона
     *
     * @param User $user
     *
     * @return Page
     */
    public function ban(User $user)
    {
        $this->httpStatus = 403;
        $this->nameTpl    = 'ban';
#       $this->onlinePos  = 'ban';
#       $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Info');
        $this->ban        = $user->banInfo;
        $this->adminEmail = $this->c->config->o_admin_email;

        return $this;
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare()
    {
    }
}
