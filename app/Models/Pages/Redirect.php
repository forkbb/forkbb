<?php

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
        return $this->page('Index'); //->message('Redirecting to index');
    }

    /**
     * Задает адрес перехода
     */
    public function page(string $marker, array $args = []): Page
    {
        $this->link = $this->c->Router->link(
            $marker,
            $args
        );

        return $this;
    }

    /**
     * Задает ссылку для перехода
     */
    public function url(string $url): Page
    {
        $this->link = $url;

        return $this;
    }

    /**
     * Задает сообщение
     */
    public function message(string $message): Page
    {
        // переадресация без вывода сообщения
        if ('0' == $this->c->config->o_redirect_delay) {
            return $this;
        }

        $this->nameTpl = 'layouts/redirect';
        $this->titles  = __('Redirecting');
        $this->robots  = 'noindex';
        $this->message = __($message) . ' ' . __('Redirecting...');
        $this->timeout = (int) $this->c->config->o_redirect_delay;  //???? перенести в заголовки?

        return $this;
    }

    /**
     * Возвращает HTTP заголовки страницы
     * $this->httpHeaders
     */
    protected function getHttpHeaders(): array
    {
        if (
            '0' == $this->c->config->o_redirect_delay
            || null === $this->nameTpl
        ) {
            $this->httpStatus = 302;
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
