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
     * @param array $data
     * @param Container $container
     */
    public function __construct(array $data = [], Container $container)
    {
        $this->now = time();
        parent::__construct($data, $container);
    }

    protected function getIsUnverified()
    {
        return $this->group_id == $this->c->GROUP_UNVERIFIED;
    }

    protected function getIsGuest()
    {
        return $this->group_id == $this->c->GROUP_GUEST
            || $this->id < 2
            || $this->group_id == $this->c->GROUP_UNVERIFIED;
    }

    protected function getIsAdmin()
    {
        return $this->group_id == $this->c->GROUP_ADMIN;
    }

    protected function getIsAdmMod()
    {
        return $this->group_id == $this->c->GROUP_ADMIN
            || $this->g_moderator == '1';
    }

    protected function getLogged()
    {
        return empty($this->a['logged']) ? $this->now : $this->a['logged'];
    }

    protected function getIsLogged()
    {
        return ! empty($this->a['logged']);
    }

    protected function getLanguage()
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

    protected function getStyle()
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
}
