<?php

declare(strict_types=1);

namespace ForkBB\Models\Group;

use ForkBB\Models\DataModel;

class Model extends DataModel
{
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
            $this->c->GROUP_ADMIN,
            $this->c->GROUP_MOD,
            $this->c->GROUP_GUEST,
            $this->c->GROUP_MEMBER
        ];

        return ! \in_array($this->g_id, $notDeleted) && $this->g_id !== $this->c->config->i_default_user_group;
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
        return $this->g_id === $this->c->GROUP_GUEST;
    }

    /**
     * Группа пользователей
     */
    protected function getgroupMember(): bool
    {
        return $this->g_id === $this->c->GROUP_MEMBER;
    }

    /**
     * Группа админов
     */
    protected function getgroupAdmin(): bool
    {
        return $this->g_id === $this->c->GROUP_ADMIN;
    }
}
