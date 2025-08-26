<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\User\User;
use SensitiveParameter;
use function \ForkBB\__;

abstract class Profile extends Page
{
    /**
     * Инициализирует профиль
     */
    protected function initProfile(int $id): bool
    {
        $this->curUser = $this->c->users->load($id);

        if (
            ! $this->curUser instanceof User
            || $this->curUser->isGuest
            || (
                $this->curUser->isUnverified
                && ! $this->user->isAdmMod
            )
        ) {
            return false;
        }

        $this->c->Lang->load('profile');

        $this->identifier = 'profile';
        $this->hhsLevel   = 'secure';
        $this->rules      = $this->c->ProfileRules->setUser($this->curUser);
        $this->robots     = 'noindex';
        $this->fIndex     = $this->rules->my ? self::FI_PROFL : self::FI_USERS;
        $this->nameTpl    = 'profile';
        $this->onlinePos  = 'profile-' . $this->curUser->id; // ????

        $this->mDescription = __(['mDescription for %s', $this->curUser->username]);

        return true;
    }

    /**
     * Проверяет пароль на совпадение с текущим пользователем
     */
    public function vCheckPassword(Validator $v, #[SensitiveParameter] string $password): string
    {
        if (! \password_verify($password, $this->user->password)) {
            $v->addError('Invalid passphrase');
        }

        return $password;
    }

    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     */
    protected function crumbs(mixed ...$crumbs): array
    {
        $crumbs[] = [$this->curUser->link, ['User %s', $this->curUser->username]];
        $crumbs[] = [$this->c->Router->link('Userlist'), 'User list', null, 'users'];

        $result = parent::crumbs(...$crumbs);

        $this->profileHeader = \end($result)[1];

        return $result;
    }

    /**
     * Формирует массив кнопок
     */
    protected function btns(string $type): array
    {
        $btns = [];

        if (
            $this->user->isAdmin
            && ! $this->rules->editProfile
        ) {
            $btns['change-user-group'] = [
                $this->linkChangeGroup(),
                __('Change user group'),
            ];
        }

        if ($this->rules->banUser) {
            $id = $this->c->bans->banFromName($this->curUser->username);

            if ($id > 0) {
                $btns['unban-user'] = [
                    $this->c->Router->link(
                        'AdminBansDelete',
                        [
                            'id'  => $id,
                            'uid' => $this->curUser->id,
                        ]
                    ),
                    __('Unban user'),
                ];

            } else {
                $btns['ban-user'] = [
                    $this->c->Router->link(
                        'AdminBansNew',
                        [
                            'ids' => $this->curUser->id,
                            'uid' => $this->curUser->id,
                        ]
                    ),
                    __('Ban user'),
                ];
            }
        }

        if ($this->rules->deleteUser) {
            $btns['delete-user'] = [
                $this->c->Router->link(
                    'AdminUsersAction',
                    [
                        'action' => 'delete',
                        'ids'    => $this->curUser->id,
                    ]
                ), // ????
                __('Delete user'),
            ];
        }

        if (
            'edit' != $type
            && $this->rules->editProfile
        ) {
            $btns['edit-profile'] = [
                $this->c->Router->link(
                    'EditUserProfile',
                    [
                        'id' => $this->curUser->id,
                    ]
                ),
                __('Edit '),
            ];
        }

        if ('view' != $type) {
            $btns['view-profile'] = [
                $this->curUser->link,
                __('View '),
            ];
        }

        if (
            'config' != $type
            && $this->rules->editConfig
        ) {
            $btns['edit-settings'] = [
                $this->c->Router->link(
                    'EditUserBoardConfig',
                    [
                        'id' => $this->curUser->id,
                    ]
                ),
                __('Configure '),
            ];
        }

        return $btns;
    }

    /**
     * Формирует ссылку на изменение группы пользователя
     */
    protected function linkChangeGroup(): string
    {
        return $this->c->Router->link(
            'AdminUsersAction',
            [
                'action' => 'change_group',
                'ids'    => $this->curUser->id,
            ]
        );
    }
}
