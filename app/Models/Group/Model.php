<?php

namespace ForkBB\Models\Group;

use ForkBB\Models\DataModel;
use RuntimeException;
use InvalidArgumentException;

class Model extends DataModel
{
    protected function getlinkEdit()
    {
        return $this->c->Router->link('AdminGroupsEdit', ['id' => $this->g_id]);
    }

    protected function getcanDelete()
    {
        $notDeleted = [
            $this->c->GROUP_ADMIN,
            $this->c->GROUP_MOD,
            $this->c->GROUP_GUEST,
            $this->c->GROUP_MEMBER
        ];
        return ! \in_array($this->g_id, $notDeleted) && $this->g_id != $this->c->config->o_default_user_group;
    }

    protected function getlinkDelete()
    {
        return $this->canDelete ? $this->c->Router->link('AdminGroupsDelete', ['id' => $this->g_id]) : null;
    }

    protected function getgroupGuest()
    {
        return $this->g_id === $this->c->GROUP_GUEST;
    }

    protected function getgroupMember()
    {
        return $this->g_id === $this->c->GROUP_MEMBER;
    }

    protected function getgroupAdmin()
    {
        return $this->g_id === $this->c->GROUP_ADMIN;
    }
}
