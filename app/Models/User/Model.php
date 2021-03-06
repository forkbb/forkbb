<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\User;

use ForkBB\Core\Container;
use ForkBB\Models\DataModel;
use ForkBB\Models\Model as BaseModel;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Post\Model as Post;
use RuntimeException;
use function \ForkBB\__;

class Model extends DataModel
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->zDepend = [
            'group_id'     => ['isUnverified', 'isGuest', 'isAdmin', 'isAdmMod', 'isBanByName', 'link', 'viewUsers', 'showPostCount', 'searchUsers', 'usePoll', 'usePM'],
            'id'           => ['isGuest', 'link', 'online'],
            'last_visit'   => ['currentVisit'],
            'show_sig'     => ['showSignature'],
            'show_avatars' => ['showAvatar'],
            'signature'    => ['isSignature'],
            'email'        => ['email_normal'],
            'g_pm'         => ['usePM'],
        ];
    }

    /**
     * Статус неподтвержденного
     */
    protected function getisUnverified(): bool
    {
        return 0 === $this->group_id;
    }

    /**
     * Статус гостя
     */
    protected function getisGuest(): bool
    {
        return $this->group_id === $this->c->GROUP_GUEST
            || $this->id < 2
            || null === $this->group_id;
    }

    /**
     * Статус админа
     */
    protected function getisAdmin(): bool
    {
        return $this->group_id === $this->c->GROUP_ADMIN;
    }

    /**
     * Статус админа/модератора
     */
    protected function getisAdmMod(): bool
    {
        return $this->group_id === $this->c->GROUP_ADMIN
            || 1 == $this->g_moderator;
    }

    /**
     * Статус бана по имени пользователя
     */
    protected function getisBanByName(): bool
    {
        return ! $this->isAdmin
            && $this->c->bans->banFromName($this->username) > 0;
    }

    /**
     * Статус модератора для указанной модели
     */
    public function isModerator(BaseModel $model): bool
    {
        if (1 != $this->g_moderator) {
            return false;
        }

        while (! $model instanceof Forum) {
            $model = $model->parent;
            if (! $model instanceof BaseModel) {
                throw new RuntimeException('Moderator\'s rights can not be found');
            }
        }

        return isset($model->moderators[$this->id]);
    }

    /**
     * Время последнего (или текущего) визита
     */
    protected function getcurrentVisit(): int
    {
        return $this->c->Online->currentVisit($this) ?? $this->last_visit;
    }

    /**
     * Текущий язык пользователя
     */
    protected function getlanguage(): string
    {
        $langs = $this->c->Func->getLangs();
        $lang  = $this->getAttr('language');

        if (
            empty($lang)
            || ! isset($langs[$lang])
        ) {
            $lang = $this->c->config->o_default_lang;
        }

        if (isset($langs[$lang])) {
            return $lang;
        } else {
            return \reset($langs) ?: 'en';
        }
    }

    /**
     * Текущий стиль отображения
     */
    protected function getstyle(): string
    {
        $styles = $this->c->Func->getStyles();
        $style  = $this->getAttr('style');

        if (
            $this->isGuest
            || empty($style)
            || ! isset($styles[$style])
        ) {
            $style = $this->c->config->o_default_style;
        }

        if (isset($styles[$style])) {
            return $style;
        } else {
            return \reset($styles) ?: 'ForkBB';
        }
    }

    /**
     * Ссылка на профиль пользователя
     */
    protected function getlink(): ?string
    {
        if ($this->isGuest) {
            return null;
        } else {
            return $this->c->Router->link(
                'User',
                [
                    'id'   => $this->id,
                    'name' => $this->username,
                ]
            );
        }
    }

    /**
     * Ссылка на аватару пользователя
     */
    protected function getavatar(): ?string
    {
        $file = $this->getAttr('avatar');

        if (! empty($file)) {
            $path = $this->c->DIR_PUBLIC . "{$this->c->config->o_avatars_dir}/{$file}";

            if (\is_file($path)) {
                return $this->c->PUBLIC_URL . "{$this->c->config->o_avatars_dir}/{$file}";
            }
        }

        return null;
    }

    /**
     * Удаляет аватару пользователя
     */
    public function deleteAvatar(): void
    {
        $file = $this->getAttr('avatar');

        if (! empty($file)) {
            $path = $this->c->DIR_PUBLIC . "{$this->c->config->o_avatars_dir}/{$file}";

            if (\is_file($path)) {
                @\unlink($path);
            }

            $this->avatar = '';
        }
    }

    /**
     * Титул пользователя
     */
    public function title(): string
    {
        if ($this->isBanByName) {
            return __('Banned');
        } elseif ('' != $this->title) {
            return $this->censorTitle;
        } elseif ('' != $this->g_user_title) {
            return $this->censorG_user_title;
        } elseif ($this->isGuest) {
            return __('Guest');
        } elseif ($this->isUnverified) {
            return __('Unverified');
        } else {
            return __('Member');
        }
    }

    /**
     * Статус online
     */
    protected function getonline(): bool
    {
        return $this->c->Online->isOnline($this);
    }

    /**
     * Статус наличия подписи
     */
    protected function getisSignature(): bool
    {
        return $this->g_sig_length > 0
            && $this->g_sig_lines > 0
            && '' != $this->signature;
    }

    /**
     * HTML код подписи
     */
    protected function gethtmlSign(): string
    {
        return $this->isSignature
            ? $this->c->censorship->censor($this->c->Parser->parseSignature($this->signature))
            : '';
    }

    /**
     * Статус видимости профилей пользователей
     */
    protected function getviewUsers(): bool
    {
        return 1 == $this->g_view_users || $this->isAdmin;
    }

    /**
     * Статус поиска пользователей
     */
    protected function getsearchUsers(): bool
    {
        return 1 == $this->g_search_users || $this->isAdmin;
    }

    /**
     * Статус показа аватаров
     */
    protected function getshowAvatar(): bool
    {
        return '1' == $this->c->config->o_avatars && 1 == $this->show_avatars;
    }

    /**
     * Статус показа информации пользователя
     */
    protected function getshowUserInfo(): bool
    {
        return '1' == $this->c->config->o_show_user_info;
    }

    /**
     * Статус показа подписи
     */
    protected function getshowSignature(): bool
    {
        return 1 == $this->show_sig;
    }

    /**
     * Статус показа количества сообщений
     */
    protected function getshowPostCount(): bool
    {
        return '1' == $this->c->config->o_show_post_count || $this->isAdmMod;
    }

    /**
     * Число тем на одну страницу
     */
    protected function getdisp_topics(): int
    {
        $attr = $this->getAttr('disp_topics');

        if ($attr < 10) {
            $attr = $this->c->config->i_disp_topics_default;
        }

        return $attr;
    }

    /**
     * Число сообщений на одну страницу
     */
    protected function getdisp_posts(): int
    {
        $attr = $this->getAttr('disp_topics');

        if ($attr < 10) {
            $attr = $this->c->config->i_disp_posts_default;
        }

        return $attr;
    }

    /**
     * Ссылка для продвижения пользователя из указанного сообщения
     */
    public function linkPromote(Post $post): ?string
    {
        if (
            (
                $this->isAdmin
                || (
                    $this->isAdmMod
                    && 1 == $this->g_mod_promote_users
                )
            )
            && $this->id !== $post->user->id //????
            && 0 < $post->user->g_promote_min_posts * $post->user->g_promote_next_group
            && ! $post->user->isBanByName
        ) {
            return $this->c->Router->link(
                'AdminUserPromote',
                [
                    'uid' => $post->user->id,
                    'pid' => $post->id,
                ]
            );
        } else {
            return null;
        }
    }

    /**
     * Вычисление нормализованного email
     */
    protected function getemail_normal(): string
    {
        return $this->c->NormEmail->normalize($this->email);
    }

    /**
     * Возвращает значения свойств в массиве
     */
    public function getAttrs(): array
    {
        if (isset($this->zModFlags['email_normal'])) {
            $this->setAttr('email_normal', $this->email_normal);
        }

        return parent::getAttrs();
    }

    /**
     * Статус возможности использования опросов
     */
    protected function getusePoll(): bool
    {
        return 1 === $this->c->config->b_poll_enabled && ! $this->isGuest;
    }

    public function fLog(): string
    {
        return "id:{$this->id} gid:{$this->group_id} name:{$this->username}";
    }

    /**
     * Статус возможности использования приватных сообщений
     */
    protected function getusePM(): bool
    {
        return 1 === $this->c->config->b_pm
            && (
                1 == $this->g_pm
                || $this->isAdmin
            );
    }
}
