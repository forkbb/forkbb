<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Rules;

use ForkBB\Models\Model;
use ForkBB\Models\Rules;
use ForkBB\Models\User\User;

class Profile extends Rules
{
    protected User $curUser;
    protected User $user;

    /**
     * Задает профиль пользователя для применения правил
     */
    public function setUser(User $curUser): Profile
    {
        $this->setModelAttrs([]);

        $this->ready       = true;
        $this->user        = $this->c->user;
        $this->curUser     = $curUser;
        $this->my          = ! $curUser->isGuest && $curUser->id === $this->user->id;
        $this->admin       = $this->user->isAdmin && ($this->my || ! $curUser->isAdmin);
        $this->moderator   = $this->user->isAdmMod && ($this->my || ! $curUser->isAdmMod);
        $this->editProfile = $this->my || $this->admin || ($this->moderator && 1 === $this->user->g_mod_edit_users);
        $this->editConfig  = $this->my || $this->admin || ($this->moderator && 1 === $this->user->g_mod_edit_users); // ????

        return $this;
    }

    protected function getrename(): bool
    {
        return ! $this->curUser->isBanByName
            && (
                $this->admin
                || ($this->moderator && 1 === $this->user->g_mod_rename_users)
            );
    }

    protected function geteditPass(): bool
    {
        return $this->my || $this->admin || ($this->moderator && 1 === $this->user->g_mod_change_passwords);
    }

    protected function getsetTitle(): bool
    {
        return $this->admin || $this->moderator || 1 === $this->user->g_set_title;
    }

    protected function getviewOEmail(): bool
    {
        return $this->my || $this->user->isAdmMod;
    }

    protected function geteditEmail(): bool
    {
        return $this->my || $this->admin;
    }

    protected function getconfirmEmail(): bool
    {
        return $this->my && ! $this->curUser->email_confirmed;
    }

    protected function getsendPM(): bool
    {
        return ! $this->my
            && $this->user->usePM
            && 1 === $this->user->u_pm
            && $this->curUser->usePM
            && (
                1 === $this->curUser->u_pm
                || $this->user->isAdmin
            );
    }

    protected function getviewLastVisit(): bool
    {
        return $this->my || $this->user->isAdmMod;
    }

    protected function getbanUser(): bool
    {
        return ! $this->my
            && ($this->admin || ($this->moderator && 1 === $this->user->g_mod_ban_users))
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

    protected function getdeleteMyProfile(): bool
    {
        return $this->my
            && ! $this->user->isAdmin
            && 1 === $this->curUser->g_delete_profile;
    }

    protected function getviewIP(): bool
    {
        return $this->user->isAdmin;;
    }

    protected function getuseAvatar(): bool
    {
        return 1 === $this->c->config->b_avatars;
    }

    protected function getuseSignature(): bool
    {
        return $this->curUser->g_sig_length > 0 && $this->curUser->g_sig_lines > 0;
    }

    protected function getviewWebsite(): bool
    {
        return $this->user->isAdmMod || 1 === $this->curUser->g_post_links;
    }

    protected function geteditWebsite(): bool
    {
        return $this->admin || (($this->moderator || $this->my) && 1 === $this->user->g_post_links); //????
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

    protected function getviewSubscription(): bool
    {
        return (
                $this->my
                || $this->admin
            )
            && (
                1 === $this->c->config->b_forum_subscriptions
                || 1 === $this->c->config->b_topic_subscriptions
            );
    }

    protected function getconfigureOAuth(): bool
    {
        return $this->my && 1 === $this->c->config->b_oauth_allow;
    }

    protected function getconfigureSearch(): bool
    {
        return $this->my && 1 === $this->curUser->g_search;
    }
}
