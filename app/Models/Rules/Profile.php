<?php

namespace ForkBB\Models\Rules;

use ForkBB\Models\Model;
use ForkBB\Models\Rules;
use ForkBB\Models\User\Model as User;

class Profile extends Rules
{
    protected $curUser;

    protected $user;

    /**
     * Задает профиль пользователя для применения правил
     *
     * @param User $curUser
     *
     * @return Rules\Profile
     */
    public function setUser(User $curUser): Profile
    {
        $this->setAttrs([]);

        $this->ready       = true;
        $this->user        = $this->c->user;
        $this->curUser     = $curUser;
        $this->my          = ! $curUser->isGuest && $curUser->id === $this->user->id;
        $this->admin       = $this->user->isAdmin && ($this->my || ! $curUser->isAdmin);
        $this->moderator   = $this->user->isAdmMod && ($this->my || ! $curUser->isAdmMod);
        $this->editProfile = $this->my || $this->admin || ($this->moderator && '1' == $this->user->g_mod_edit_users);
        $this->editConfig  = $this->my || $this->admin || ($this->moderator && '1' == $this->user->g_mod_edit_users); // ????

        return $this;
    }

    protected function getrename(): bool
    {
        return $this->admin || ($this->moderator && '1' == $this->user->g_mod_rename_users);
    }

    protected function geteditPass(): bool
    {
        return $this->my || $this->admin || ($this->moderator && '1' == $this->user->g_mod_change_passwords);
    }

    protected function getsetTitle(): bool
    {
        return $this->admin || $this->moderator || '1' == $this->user->g_set_title;
    }

    protected function getviewOEmail(): bool
    {
        return $this->my || $this->user->isAdmMod;
    }

    protected function getviewEmail(): bool
    {
        return ! $this->my
            && (
                $this->user->isAdmMod // ???? модераторы у админов должны видеть email?
                || (
                    ! $this->user->isGuest
                    && ! $this->user->isAdmMod
                    && '1' == $this->user->g_send_email
                    && $this->curUser->email_setting < 2
                )
            );
    }

    protected function geteditEmail(): bool
    {
        return $this->my || $this->admin;
    }

    protected function getsendEmail(): ?bool // ???? проверка на подтвержденный email?
    {
        if ($this->viewEmail) {
            if ($this->user->isAdmMod) {
                return true;
            } elseif (1 === $this->curUser->email_setting) {
                return true;
            }
        } elseif (2 === $this->curUser->email_setting) {
            return null;
        }

        return false;
    }

    protected function getviewLastVisit(): bool
    {
        return $this->my || $this->user->isAdmMod;
    }

    protected function getbanUser(): bool
    {
        return ! $this->my
            && ($this->admin || ($this->moderator && '1' == $this->user->g_mod_ban_users))
            && ! $this->curUser->isAdmMod
            && ! $this->curUser->isGuest;
    }

    protected function getdeleteUser(): bool
    {
        return ! $this->my
            && $this->admin
            && ! $this->curUser->isAdmMod
            && ! $this->curUser->isGuest;
    }

    protected function getviewIP(): bool
    {
        return $this->user->isAdmin;;
    }

    protected function getuseAvatar(): bool
    {
        return '1' == $this->c->config->o_avatars;
    }

    protected function getuseSignature(): bool
    {
        return '1' == $this->curUser->g_sig_use;
    }

    protected function getviewWebsite(): bool
    {
        return $this->user->isAdmMod || '1' == $this->curUser->g_post_links;
    }

    protected function geteditWebsite(): bool
    {
        return $this->admin || (($this->moderator || $this->my) && '1' == $this->user->g_post_links); //????
    }

    protected function getchangeGroup(): bool
    {
        return $this->admin || ($this->my && $this->moderator);
    }

    protected function getconfModer(): bool
    {
        return $this->user->isAdmin && $this->curUser->isAdmMod && ! $this->curUser->isAdmin;
    }

    protected function geteditIpCheckType(): bool
    {
        return $this->my || $this->admin;
    }
}
