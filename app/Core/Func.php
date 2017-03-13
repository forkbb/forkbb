<?php

namespace ForkBB\Core;

class Func
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Список доступных стилей
     * @var array
     */
    protected $styles;

    /**
     * Список доступных языков
     * @var array
     */
    protected $langs;

    /**
     * Конструктор
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Список доступных стилей
     *
     * @return array
     */
    public function getStyles()
    {
        if (empty($this->styles)) {
            $this->styles = array_map(function($style) {
                return str_replace([$this->c->DIR_PUBLIC . '/style/', '/style.css'], '', $style);
            }, glob($this->c->DIR_PUBLIC . '/style/*/style.css'));
        }
        return $this->styles;
    }

    /**
     * Список доступных языков
     *
     * @return array
     */
    public function getLangs()
    {
        if (empty($this->langs)) {
            $this->langs = array_map(function($lang) {
                return str_replace([$this->c->DIR_LANG . '/', '/common.po'], '', $lang);
            }, glob($this->c->DIR_LANG . '/*/common.po'));
        }
        return $this->langs;
    }
}
