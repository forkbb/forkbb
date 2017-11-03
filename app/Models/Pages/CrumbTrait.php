<?php

namespace ForkBB\Models\Pages;

trait CrumbTrait 
{
    /**
     * Возвращает массив хлебных крошек
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
            if (isset($arg->forum_name)) {
                while ($arg->id > 0) {
                    $this->titles = $arg->forum_name;
                    $crumbs[] = [
                        $this->c->Router->link('Forum', ['id' => $arg->id, 'name' => $arg->forum_name]),
                        $arg->forum_name,
                        $active,
                    ];
                    $active = null;
                    $arg = $arg->parent;
                }
            } else {
                $this->titles = (string) $arg;
                $crumbs[] = [
                    null,
                    (string) $arg,
                    $active,
                ];
            }
/*
            if (is_array($arg)) {
                $cur = array_shift($arg);
                // массив разделов
                if (is_array($cur)) {
                    $id = $arg[0];
                    while (true) {
                        $this->titles = $cur[$id]['forum_name'];
                        $crumbs[] = [
                            $this->c->Router->link('Forum', ['id' => $id, 'name' => $cur[$id]['forum_name']]),
                            $cur[$id]['forum_name'], 
                            $active,
                        ];
                        $active = null;
                        if (! isset($cur[$id][0])) {
                            break;
                        }
                        $id = $cur[$id][0];
                    }
                // отдельная страница
                } else {
                    // определение названия
                    if (isset($arg[1])) {
                        $vars = $arg[0];
                        $name = $arg[1];
                    } elseif (is_string($arg[0])) {
                        $vars = [];
                        $name = $arg[0];
                    } elseif (isset($arg[0]['name'])) {
                        $vars = $arg[0];
                        $name = $arg[0]['name'];
                    } else {
                        continue;
                    }
                    $this->titles = $name;
                    $crumbs[] = [
                        $this->c->Router->link($cur, $vars),
                        $name, 
                        $active,
                    ];
                }
            // предположительно идет только название, без ссылки
            } else {
                $this->titles = (string) $arg;
                $crumbs[] = [
                    null,
                    (string) $arg,
                    $active,
                ];
            }
*/
            $active = null;
        }
        // главная страница
        $crumbs[] = [
            $this->c->Router->link('Index'),
            __('Index'),
            $active,
        ];

        return array_reverse($crumbs);
    }
}
