<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Models\User;

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
        $ban = $user->banInfo;
        
        if (! empty($ban['expire'])) {
            $ban['expire'] = strtolower($this->time($ban['expire'], true));
        }

        $this->httpStatus = 403;
        $this->nameTpl    = 'ban';
#       $this->onlinePos  = 'ban';
#       $this->robots     = 'noindex';
        $this->titles     = __('Info');
        $this->ban        = $ban;
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
