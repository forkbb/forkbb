<?php

namespace ForkBB\Models\Rules;

use ForkBB\Models\Model;
use ForkBB\Models\Rules;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Profile extends Rules
{
    protected $curUser;

    protected $user;

    /**
     * Задает профиль пользователя для применения правил
     *
     * @param User $curUser
     *
     * @return ProfileRules
     */
    public function setUser(User $curUser)
    {
        $this->ready = true;

        $this->user        = $this->c->user;
        $this->curUser     = $curUser;
        $this->my          = $curUser->id === $this->user->id;
        $this->admin       = $this->user->isAdmin && ($this->my || ! $curUser->isAdmin);
        $this->moderator   = $this->user->isAdmMod && ($this->my || ! $curUser->isAdmMod);
        $this->editProfile = $this->my || $this->admin || ($this->moderator && '1' == $this->user->g_mod_edit_users);
        $this->editConfig  = $this->my || $this->admin || ($this->moderator && '1' == $this->user->g_mod_edit_users); // ????

        return $this;
    }

    public function getrename()
    {
        return $this->admin || ($this->moderator  && '1' == $this->user->g_mod_rename_users);
    }

    public function getsetTitle()
    {
        return $this->admin || $this->moderator || '1' == $this->user->g_set_title;
    }

    public function getviewOEmail()
    {
        return $this->my || $this->user->isAdmMod;
    }

    public function getviewEmail() // ?????
    {
        return ! $this->my
            && (($this->user->isAdmMod && 1 === $this->curUser->email_setting)
                || (! $this->user->isGuest && '1' == $this->user->g_send_email)
            );
    }

    public function getviewLastVisit()
    {
        return $this->my || $this->user->isAdmMod;
    }

    public function getbanUser()
    {
        return ! $this->my && ($this->admin || ($this->moderator && '1' == $this->user->g_mod_ban_users));
    }

    public function getdeleteUser()
    {
        return ! $this->my && ($this->admin || $this->moderator); // ????
    }

    public function getviewIP()
    {
        return $this->user->isAdmin;
    }

    public function getuseAvatar()
    {
        return '1' == $this->c->config->o_avatars;
    }
}
