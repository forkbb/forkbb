<?php

namespace ForkBB\Models;

use ForkBB\Models\DataModel;
use ForkBB\Core\Container;
use RuntimeException;

class Post extends DataModel
{
    protected function getlink()
    {
        return $this->c->Router->link('ViewPost', ['id' => $this->id]);
    }

    protected function getuser()
    {
        $attrs = $this->a; //????
        $attrs['id'] = $attrs['poster_id'];
        return $this->c->ModelUser->setAttrs($attrs);
    }

    protected function getshowUserLink()
    {
        return $this->c->user->g_view_users == '1';
    }

    protected function getshowUserAvatar()
    {
        return $this->c->config->o_avatars == '1' && $this->c->user->show_avatars == '1';
    }

    protected function getshowUserInfo()
    {
        return $this->c->config->o_show_user_info == '1';
    }

    protected function getshowSignature()
    {
        return $this->c->config->o_signatures == '1' && $this->c->user->show_sig == '1';
    }

    protected function getcontrols()
    {
        $user = $this->c->user;
        $controls = [];
        $vars = ['id' => $this->id];
        if (! $user->isAdmin && ! $user->isGuest) {
            $controls['report'] = [$this->c->Router->link('ReportPost', $vars), 'Report'];
        }
        if ($user->isAdmin
            || ($user->isAdmMod 
                && ! $this->user->isAdmin
                && isset($this->parent->parent->moderators[$user->id]) 
            )
        ) {
            $controls['delete'] = [$this->c->Router->link('DeletePost', $vars), 'Delete'];
            $controls['edit'] = [$this->c->Router->link('EditPost', $vars), 'Edit'];
        } elseif ($this->parent->closed != '1'
            && $this->user->id == $user->id
            && ($user->g_deledit_interval == '0' 
                || $this->edit_post == '1' 
                || time() - $this->posted < $user->g_deledit_interval
            )
        ) {
            if (($this->id == $this->parent->first_post_id && $user->g_delete_topics == '1') 
                || ($this->id != $this->parent->first_post_id && $user->g_delete_posts == '1')
            ) {
                $controls['delete'] = [$this->c->Router->link('DeletePost', $vars), 'Delete'];
            }
            if ($user->g_edit_posts == '1') {
                $controls['edit'] = [$this->c->Router->link('EditPost', $vars), 'Edit'];
            }
        }
        if ($this->parent->post_replies) {
            $controls['quote'] = [$this->c->Router->link('NewReply', ['id' => $this->parent->id, 'quote' => $this->id]), 'Reply'];
        }
        return $controls;
    }
}
