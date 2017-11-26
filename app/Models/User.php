<?php

namespace ForkBB\Models;

use ForkBB\Models\DataModel;
use ForkBB\Core\Container;

class User extends DataModel
{
    /**
     * Время
     * @var int
     */
    protected $now;

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->now = time();
        parent::__construct($container);
    }

    protected function getisUnverified()
    {
        return $this->group_id == $this->c->GROUP_UNVERIFIED;
    }

    protected function getisGuest()
    {
        return $this->group_id == $this->c->GROUP_GUEST
            || $this->id < 2
            || $this->group_id == $this->c->GROUP_UNVERIFIED;
    }

    protected function getisAdmin()
    {
        return $this->group_id == $this->c->GROUP_ADMIN;
    }

    protected function getisAdmMod()
    {
        return $this->group_id == $this->c->GROUP_ADMIN
            || $this->g_moderator == '1';
    }

    protected function getlogged()
    {
        return empty($this->a['logged']) ? $this->now : $this->a['logged'];
    }

    protected function getisLogged()
    {
        return ! empty($this->a['logged']);
    }

    protected function getlanguage()
    {
        $langs = $this->c->Func->getLangs();

        $lang = $this->isGuest || empty($this->a['language']) || ! in_array($this->a['language'], $langs)
            ? $this->c->config->o_default_lang
            : $this->a['language'];

        if (in_array($lang, $langs)) {
            return $lang;
        } else {
            return isset($langs[0]) ? $langs[0] : 'English';
        }
    }

    protected function getstyle()
    {
        $styles = $this->c->Func->getStyles();

        $style = $this->isGuest || empty($this->a['style']) || ! in_array($this->a['style'], $styles)
            ? $this->c->config->o_default_style
            : $this->a['style'];

        if (in_array($style, $styles)) {
            return $style;
        } else {
            return isset($styles[0]) ? $styles[0] : 'ForkBB';
        }
    }

    protected function getlink()
    {
        return $this->c->Router->link('User', ['id' => $this->id, 'name' => $this->username]);
    }

    protected function getavatar()
    {
        $filetypes = array('jpg', 'gif', 'png');
            
        foreach ($filetypes as $type) {
            $path = $this->c->DIR_PUBLIC . "/{$this->c->config->o_avatars_dir}/{$this->id}.{$type}";
        
            if (file_exists($path) && getimagesize($path)) {
                return $this->c->PUBLIC_URL . "/{$this->c->config->o_avatars_dir}/{$this->id}.{$type}";
            }
        }
    
        return null;
    }

    public function title()
    {
        if (isset($this->c->bans->userList[mb_strtolower($this->username)])) { //????
            return __('Banned');
        } elseif ($this->title != '') {
            return $this->cens()->title;
        } elseif ($this->g_user_title != '') {
            return $this->cens()->g_user_title;
        } elseif ($this->isGuest) {
            return __('Guest');
        } else {
            return __('Member');
        }
    }

    protected function getonline()
    {
        return isset($this->c->Online->online[$this->id]);
    }
}
