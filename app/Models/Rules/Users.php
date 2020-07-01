<?php

namespace ForkBB\Models\Rules;

use ForkBB\Models\Model;
use ForkBB\Models\Rules;
use ForkBB\Models\User\Model as User;
use ForkBB\Models\Rules\Profile as ProfileRules;

class Users extends Rules
{
    /**
     * Инициализирует
     *
     * @return Rules\Users
     */
    public function init(): self
    {
        $this->setAttrs([]);

        $this->ready = true;
        $this->user  = $this->c->user;

        return $this;
    }

    protected function getviewIP(): bool
    {
        return $this->user->isAdmin;
    }

    protected function getdeleteUsers(): bool
    {
        return $this->user->isAdmin;
    }

    protected function getbanUsers(): bool
    {
        return $this->user->isAdmin || ($this->user->isAdmMod && '1' == $this->user->g_mod_ban_users);
    }

    protected function getchangeGroup(): bool
    {
        return $this->user->isAdmin;
    }

    public function canDeleteUser(User $user): bool
    {
        if (! $this->profileRules instanceof ProfileRules) {
            $this->profileRules = $this->c->ProfileRules;
        }

        return $this->profileRules->setUser($user)->deleteUser;
    }

    public function canBanUser(User $user): bool
    {
        if (! $this->profileRules instanceof ProfileRules) {
            $this->profileRules = $this->c->ProfileRules;
        }

        return $this->profileRules->setUser($user)->banUser;
    }

    public function canChangeGroup(User $user, bool $profile = false): bool
    {
        if (! $this->profileRules instanceof ProfileRules) {
            $this->profileRules = $this->c->ProfileRules;
        }

        if (
            $profile
            && $this->user->isAdmin
        ) {
            return true;
        } elseif (
            ! $profile
            && $user->isAdmin
        ) {
            return false;
        }

        return $this->profileRules->setUser($user)->changeGroup;
    }
}
