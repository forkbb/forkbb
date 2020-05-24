<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Redirect extends Page
{
    /**
     * Перенаправление на главную страницу форума
     *
     * @return Page
     */
    public function toIndex(): Page
    {
        return $this->page('Index'); //->message('Redirecting to index');
    }

    /**
     * Задает адрес перехода
     *
     * @param string $marker
     * @param array $args
     *
     * @return Page
     */
    public function page(string $marker, array $args = []): Page
    {
        $this->link = $this->c->Router->link($marker, $args);
        return $this;
    }

    /**
     * Задает ссылку для перехода
     *
     * @param string $url
     *
     * @return Page
     */
    public function url(string $url): Page
    {
        $this->link = $url;
        return $this;
    }

    /**
     * Задает сообщение
     *
     * @param string $message
     *
     * @return Page
     */
    public function message(string $message): Page
    {
        // переадресация без вывода сообщения
        if ($this->c->config->o_redirect_delay == '0') {
            return $this;
        }

        $this->nameTpl = 'layouts/redirect';
        $this->titles  = \ForkBB\__('Redirecting');
        $this->robots  = 'noindex';
        $this->message = \ForkBB\__($message);
        $this->timeout = (int) $this->c->config->o_redirect_delay;  //???? перенести в заголовки?

        return $this;
    }

    /**
     * Возвращает HTTP заголовки страницы
     * $this->httpHeaders
     *
     * @return array
     */
    protected function getHttpHeaders(): array
    {
        if (null === $this->nameTpl) {
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
