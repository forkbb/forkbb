<?php

namespace ForkBB\Models\Group;

use ForkBB\Models\DataModel;

class Model extends DataModel
{
    protected function getlinkEdit(): string
    {
        return $this->c->Router->link(
            'AdminGroupsEdit',
            [
                'id' => $this->g_id,
            ]
        );
    }

    protected function getcanDelete(): bool
    {
        $notDeleted = [
            $this->c->GROUP_ADMIN,
            $this->c->GROUP_MOD,
            $this->c->GROUP_GUEST,
            $this->c->GROUP_MEMBER
        ];
        return ! \in_array($this->g_id, $notDeleted) && $this->g_id != $this->c->config->o_default_user_group;
    }

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

    protected function getgroupGuest(): bool
    {
        return $this->g_id === $this->c->GROUP_GUEST;
    }

    protected function getgroupMember(): bool
    {
        return $this->g_id === $this->c->GROUP_MEMBER;
    }

    protected function getgroupAdmin(): bool
    {
        return $this->g_id === $this->c->GROUP_ADMIN;
    }
}
