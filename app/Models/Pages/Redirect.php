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
use function \ForkBB\__;

class Redirect extends Page
{
    /**
     * Перенаправление на главную страницу форума
     */
    public function toIndex(): Page
    {
        $this->c->curReqVisible = 0;

        return $this->page('Index'); //->message('Redirecting to index');
    }

    /**
     * Задает адрес перехода
     */
    public function page(string $marker, array $args = [], int $httpStatus = 302): Page
    {
        $this->link          = $this->c->Router->link($marker, $args);
        $this->curHttpStatus = $httpStatus;

        return $this;
    }

    /**
     * Задает ссылку для перехода
     */
    public function url(string $url, int $httpStatus = 302): Page
    {
        $this->link          = $url;
        $this->curHttpStatus = $httpStatus;

        return $this;
    }

    /**
     * Задает сообщение
     */
    public function message(string|array $message, string $status = FORK_MESS_INFO, int $timeout = 0): Page
    {
        $this->c->curReqVisible = 0;

        // переадресация без вывода сообщения
        if (
            $timeout < 1
            && $this->c->config->i_redirect_delay < 1
        ) {
            return $this;
        }

        $this->nameTpl = 'layouts/redirect';
        $this->robots  = 'noindex';
        $this->fIswev  = [$status, $message];
        $this->fIswev  = [$status, ['Redirecting...', $this->link]];
        $this->timeout = $timeout > 0 ? $timeout : $this->c->config->i_redirect_delay;

        return $this;
    }

    /**
     * Возвращает HTTP заголовки страницы
     * $this->httpHeaders
     */
    protected function getHttpHeaders(): array
    {
        if (
            $this->timeout < 1
            || null === $this->nameTpl
        ) {
            $this->httpStatus = $this->curHttpStatus ?? 302;
            $this->nameTpl    = null;

            $this->header('Location', $this->link);
        }

        return parent::getHttpHeaders();
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
    }
}
