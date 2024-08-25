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
use ForkBB\Models\Post\Post;
use ForkBB\Models\User\User;
use ForkBB\Models\Rules\Profile as ProfileRules;

class Users extends Rules
{
    /**
     * Инициализирует
     */
    public function init(): Users
    {
        $this->setModelAttrs([]);

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
        return $this->user->isAdmin
            || (
                $this->user->isAdmMod
                && 1 === $this->user->g_mod_ban_users
            );
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

    /**
     * Статус возможности использования опросов
     */
    protected function getusePoll(): bool
    {
        return ! $this->user->isGuest
            && 1 === $this->c->config->b_poll_enabled;
    }

    /**
     * Статус показа количества сообщений
     */
    protected function getshowPostCount(): bool
    {
        return 1 === $this->c->config->b_show_post_count
            || $this->user->isAdmMod;
    }

    /**
     * Статус показа подписи
     */
    protected function getshowSignature(): bool
    {
        return 1 === $this->user->show_sig;
    }

    /**
     * Статус показа информации пользователя
     */
    protected function getshowUserInfo(): bool
    {
        return $this->user->isAdmin
            || 1 === $this->c->config->b_show_user_info;
    }

    /**
     * Статус показа аватаров
     */
    protected function getshowAvatar(): bool
    {
        return 1 === $this->c->config->b_avatars
            && 1 === $this->user->show_avatars;
    }

    /**
     * Статус поиска пользователей
     */
    protected function getsearchUsers(): bool
    {
        return $this->user->isAdmin
            || 1 === $this->user->g_search_users;
    }

    /**
     * Статус видимости профилей пользователей
     */
    protected function getviewUsers(): bool
    {
        return $this->user->isAdmin
            || 1 === $this->user->g_view_users;
    }

    /**
     * Статус возможности использования загрузки файлов
     */
    protected function getuseUpload(): bool
    {
        return 1 === $this->c->config->b_upload
            && 1 === $this->user->g_post_links // ???? может быть локальные ссылки разрешить в постах?
            && ! empty($this->user->g_up_ext)
            && $this->user->g_up_size_kb > 0
            && $this->user->g_up_limit_mb > 0
            && $this->user->u_up_size_mb < $this->user->g_up_limit_mb;
    }

    /**
     * Статус видимости реакций
     */
    protected function getshowReaction(): bool
    {
        return 1 === $this->c->config->b_reaction
            && (
                1 === $this->user->show_reaction
                || $this->user->isGuest
            );
    }

    /**
     * Статус показа выбранных реакций
     */
    protected function getselectedReaction(): bool
    {
        return ! $this->user->isGuest
            && $this->showReaction
            && 1 === $this->c->config->b_show_user_reaction;
    }

    /**
     * Статус возможности реагировать на пост
     */
    public function canUseReaction(Post $post): bool
    {
        return $this->showReaction
            && ! $this->user->isGuest
            && 1 === $this->user->g_use_reaction
            && $this->user->id !== $post->poster_id;
    }
}
