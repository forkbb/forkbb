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
     *
     * @param Container $container
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
            $this->styles = \array_map(function($style) {
                return \str_replace([$this->c->DIR_PUBLIC . '/style/', '/style.css'], '', $style);
            }, \glob($this->c->DIR_PUBLIC . '/style/*/style.css'));
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
            $this->langs = \array_map(function($lang) {
                return \str_replace([$this->c->DIR_LANG . '/', '/common.po'], '', $lang);
            }, \glob($this->c->DIR_LANG . '/*/common.po'));
        }
        return $this->langs;
    }

    /**
     * Пагинация
     *
     * @param int $all
     * @param int $cur
     * @param string $marker
     * @param array $args
     * @param string $info
     *
     * @return array
     */
    public function paginate($all, $cur, $marker, array $args = [], $info = 'Page %1$s of %2$s')
    {
        $pages = [];
        if ($all < 2) {
//            $pages[] = [null, 1, true];
        } else {
            if ($cur > 0) {
                $pages[] = [\ForkBB\__($info, $cur, $all), 'info', null];
                $cur = \min(\max(1, $cur), $all);
                if ($cur > 1) {
                    $pages[] = [$this->c->Router->link($marker, ['page' => $cur - 1] + $args), 'prev', null];
                }
                $tpl = [1 => 1];
                $start = $cur < 6 ? 2 : $cur - 2;
                $end = $all - $cur < 5 ? $all : $cur + 3;
                for ($i = $start; $i < $end; ++$i) {
                    $tpl[$i] = $i;
                }
                $tpl[$all] = $all;
            } else {
                $tpl = $all < 7
                    ? \array_slice([2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6], 0, $all - 1)
                    : [2 => 2, 3 => 3, 4 => 4, $all => $all];
            }
            $k = 1;
            foreach ($tpl as $i) {
                if ($i - $k > 1) {
                    $pages[] = [null, 'space', null];
                }
                if ($i === $cur) {
                    $pages[] = [null, $i, true];
                } else {
                    $pages[] = [$this->c->Router->link($marker, ['page' => $i] + $args), $i, null];
                }
                $k = $i;
            }
            if ($cur > 0 && $cur < $all) {
                $pages[] = [$this->c->Router->link($marker, ['page' => $cur + 1] + $args), 'next', null];
            }
        }
        return $pages;
    }
}
