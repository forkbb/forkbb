<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Group;

use ForkBB\Models\DataModel;

class Group extends DataModel
{
    /**
     * Ключ модели для контейнера
     * @var string
     */
    protected $cKey = 'Group';

    /**
     * Ссылка на страницу редактирования
     */
    protected function getlinkEdit(): string
    {
        return $this->c->Router->link(
            'AdminGroupsEdit',
            [
                'id' => $this->g_id,
            ]
        );
    }

    /**
     * Статус возможности удаления
     */
    protected function getcanDelete(): bool
    {
        $notDeleted = [
            FORK_GROUP_ADMIN,
            FORK_GROUP_MOD,
            FORK_GROUP_GUEST,
            FORK_GROUP_MEMBER,
        ];

        return ! \in_array($this->g_id, $notDeleted, true) && $this->g_id !== $this->c->config->i_default_user_group;
    }

    /**
     * Ссылка на страницу удаления
     */
    protected function getlinkDelete(): ?string
    {
        return $this->canDelete
            ? $this->c->Router->link(
                'AdminGroupsDelete',
                [
                    'id' => $this->g_id,
                ]
            )
            : null;
    }

    /**
     * Группа гостей
     */
    protected function getgroupGuest(): bool
    {
        return $this->g_id === FORK_GROUP_GUEST;
    }

    /**
     * Группа пользователей
     */
    protected function getgroupMember(): bool
    {
        return $this->g_id === FORK_GROUP_MEMBER;
    }

    /**
     * Группа админов
     */
    protected function getgroupAdmin(): bool
    {
        return $this->g_id === FORK_GROUP_ADMIN;
    }
}
