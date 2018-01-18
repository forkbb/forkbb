<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Model;
use ForkBB\Models\Search\Model as Search;

trait CrumbTrait 
{
    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     * 
     * @param mixed $args
     * 
     * @return array
     */
    protected function crumbs(...$args)
    {
        $crumbs = [];
        $active = true;

        foreach ($args as $arg) {
            // поиск
            if ($arg instanceof Search) {
                if ($arg->page > 1) {
                    $this->titles = $arg->name . ' ' . \ForkBB\__('Page %s', $arg->page);
                } else {
                    $this->titles = $arg->name;
                }
                $crumbs[]     = [$arg->link, $arg->name, $active];
                $this->titles = \ForkBB\__('Search');
                $crumbs[]     = [$this->c->Router->link('Search'), \ForkBB\__('Search'), null];
            // раздел или топик
            } elseif ($arg instanceof Model) {
                while (null !== $arg->parent && $arg->link) {
                    if (isset($arg->forum_name)) {
                        $name = $arg->forum_name;
                    } elseif (isset($arg->subject)) {
                        $name = \ForkBB\cens($arg->subject);
                    } else {
                        $name = 'no name';
                    }

                    if ($arg->page > 1) {
                        $this->titles = $name . ' ' . \ForkBB\__('Page %s', $arg->page);
                    } else {
                        $this->titles = $name;
                    }
                    $crumbs[] = [$arg->link, $name, $active];
                    $active   = null;
                    $arg      = $arg->parent;
                }
            // ссылка
            } elseif (is_array($arg)) {
                $this->titles = $arg[1];
                $crumbs[]     = [$arg[0], $arg[1], $active];
            // строка
            } else {
                $this->titles = (string) $arg;
                $crumbs[]     = [null, (string) $arg, $active];
            }
            $active = null;
        }
        // главная страница
        $crumbs[] = [$this->c->Router->link('Index'), \ForkBB\__('Index'), $active];

        return array_reverse($crumbs);
    }
}
