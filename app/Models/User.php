<?php

namespace ForkBB\Models;

use ForkBB\Core\AbstractModel;
use ForkBB\Core\Container;

class User extends AbstractModel
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * @var array
     */
    protected $config;

    /**
     * Время
     * @var int
     */
    protected $now;

    /**
     * Конструктор
     */
    public function __construct(array $data, Container $container)
    {
        $this->now = time();
        $this->c = $container;
        $this->config = $container->config;
        parent::__construct($data);
    }

    /**
     * Выполняется до конструктора родителя
     */
    protected function beforeConstruct(array $data)
    {
        return $data;
    }

    protected function getIsUnverified()
    {
        return $this->groupId == $this->c->GROUP_UNVERIFIED;
    }

    protected function getIsGuest()
    {
        return $this->groupId == $this->c->GROUP_GUEST
            || $this->id < 2
            || $this->groupId == $this->c->GROUP_UNVERIFIED;
    }

    protected function getIsAdmin()
    {
        return $this->groupId == $this->c->GROUP_ADMIN;
    }

    protected function getIsAdmMod()
    {
        return $this->groupId == $this->c->GROUP_ADMIN
            || $this->gModerator == '1';
    }

    protected function getLogged()
    {
        return empty($this->data['logged']) ? $this->now : $this->data['logged'];
    }

    protected function getIsLogged()
    {
        return ! empty($this->data['logged']);
    }

    protected function getLanguage()
    {
        $langs = $this->c->Func->getLangs();

        $lang = $this->isGuest || empty($this->data['language']) || ! in_array($this->data['language'], $langs)
            ? $this->config['o_default_lang']
            : $this->data['language'];

        if (in_array($lang, $langs)) {
            return $lang;
        } else {
            return isset($langs[0]) ? $langs[0] : 'English';
        }
    }

    protected function getStyle()
    {
        $styles = $this->c->Func->getStyles();

        $style = $this->isGuest || empty($this->data['style']) || ! in_array($this->data['style'], $styles)
            ? $this->config['o_default_style']
            : $this->data['style'];

        if (in_array($style, $styles)) {
            return $style;
        } else {
            return isset($styles[0]) ? $styles[0] : 'ForkBB';
        }
    }
}
