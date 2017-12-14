<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Model;

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
            // Раздел или топик
            if ($arg instanceof Model) {
                while (null !== $arg->parent && $arg->link) {
                    if (isset($arg->forum_name)) {
                        $name = $arg->forum_name;
                    } elseif (isset($arg->subject)) {
                        $name = \ForkBB\cens($arg->subject);
                    } else {
                        $name = 'no name';
                    }

                    if ($arg->page > 1) {
                        $this->titles = $name . ' ' . \ForkBB\__('Page', $arg->page);
                    } else {
                        $this->titles = $name;
                    }
                    $crumbs[] = [$arg->link, $name, $active];
                    $active = null;
                    $arg = $arg->parent;
                }
            // Строка
            } else {
                $this->titles = (string) $arg;
                $crumbs[] = [null, (string) $arg, $active];
            }
            $active = null;
        }
        // главная страница
        $crumbs[] = [$this->c->Router->link('Index'), \ForkBB\__('Index'), $active];

        return array_reverse($crumbs);
    }
}
