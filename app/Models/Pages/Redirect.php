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
    public function toIndex()
    {
        return $this->page('Index')->message(\ForkBB\__('Redirecting to index'));
    }

    /**
     * Задает адрес перехода
     * 
     * @param string $marker
     * @param array $args
     * 
     * @return Page
     */
    public function page($marker, array $args = [])
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
    public function url($url)
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
    public function message($message)
    {
        // переадресация без вывода сообщения
        if ($this->c->config->o_redirect_delay == '0') {
            return $this;
        }

        $this->nameTpl = 'layouts/redirect';
        $this->titles  = \ForkBB\__('Redirecting');
        $this->robots  = 'noindex';
        $this->message = $message;
        $this->timeout = (int) $this->c->config->o_redirect_delay;  //???? перенести в заголовки?

        return $this;
    }

    /**
     * Возвращает HTTP заголовки страницы
     * $this->httpHeaders
     * 
     * @return array
     */
    protected function getHttpHeaders()
    {
        if (null === $this->nameTpl) {
            $this->httpHeaders = [
                'Location: ' . $this->link, //????
            ];
        }
        return parent::getHttpHeaders();
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare()
    {
    }
}
