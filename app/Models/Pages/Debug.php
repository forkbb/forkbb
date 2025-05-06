<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
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
                2 & $this->c->DEBUG
                && $this->user->isAdmin
            ) {
                $total   = 0;
                $queries = $this->c->DB->getQueries();

                foreach ($queries as $cur) {
                    $total += $cur[1];
                }

                $this->queries  = $queries;
                $this->total    = $total;
                $this->lifeTime = $this->c->DB->getLifeTime();
            }

        } else {
            $this->numQueries = 0;
        }

        $this->nameTpl    = 'layouts/debug';
        $this->onlinePos  = null;
        $this->start      = $this->c->START;

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
