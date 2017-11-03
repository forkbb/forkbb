<?php

namespace ForkBB\Models\Pages;

trait UsersTrait 
{
    /**
     * Имена забаненных пользователей
     * 
     * @var array
     */
    protected $userBanNames;

    /**
     * Определение титула для пользователя
     * 
     * @param array $data
     * 
     * @return string
     */
    protected function userGetTitle(array $data) 
    {
        if (! isset($this->userBanNames)) {
            $this->userBanNames = $this->c->bans->userList; //????
        }

        if (isset($this->userBanNames[mb_strtolower($data['username'])])) { //????
            return __('Banned');
        } elseif ($data['title'] != '') {
            return $data['title'];
        } elseif ($data['g_user_title'] != '') {
            return $data['g_user_title'];
        } elseif ($data['g_id'] == $this->c->GROUP_GUEST) {
            return __('Guest');
        } else {
            return __('Member');
        }
    }

    /**
     * Определение ссылки на аватарку
     * 
     * @param int $id
     * 
     * @return string|null
     */
    protected function userGetAvatarLink($id)
    {
        $filetypes = array('jpg', 'gif', 'png');
    
        foreach ($filetypes as $type) {
            $path = $this->c->DIR_PUBLIC . "/{$this->c->config->o_avatars_dir}/{$id}.{$type}";

            if (file_exists($path) && getimagesize($path)) {
                return $this->c->PUBLIC_URL . "/{$this->c->config->o_avatars_dir}/{$id}.{$type}";
            }
        }

        return null;
    }
}
