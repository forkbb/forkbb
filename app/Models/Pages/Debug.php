<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Debug extends Page
{
    /**
     * Подготавливает данные для шаблона
     */
    public function debug(): Page
    {
        if ($this->c->isInit('DB')) {
            $this->numQueries = $this->c->DB->getCount();

            if (
                $this->c->DEBUG > 1
                && $this->user->isAdmin
            ) {
                $total   = 0;
                $queries = $this->c->DB->getQueries();
                foreach ($queries as $cur) {
                    $total += $cur[1];
                }
                $this->queries = $queries;
                $this->total   = $total;
            }
        } else {
            $this->numQueries = 0;
        }

        $this->nameTpl    = 'layouts/debug';
        $this->onlinePos  = null;
        $this->memory     = \memory_get_usage();
        $this->peak       = \memory_get_peak_usage();
        $this->time       = \microtime(true) - $this->c->START;

        return $this;
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
    }

    /**
     * Возвращает HTTP заголовки страницы
     * $this->httpHeaders
     */
    protected function getHttpHeaders(): array
    {
        return [];
    }
}
