<?php

namespace ForkBB\Models\Pages;

class Redirect extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = null;

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = null;

    /**
     * Адрес перехода
     * @var string
     */
    protected $link;

    /**
     * Переменная для meta name="robots"
     * @var string
     */
    protected $robots = 'noindex';

    /**
     * Возвращает флаг готовности данных
     * @return bool
     */
    public function isReady()
    {
        return ! empty($this->link);
    }

    /**
     * Перенаправление на главную страницу форума
     * @return Page
     */
    public function toIndex()
    {
        return $this->setPage('Index')->setMessage(__('Redirecting to index'));
    }

    /**
     * Задает адрес перехода
     * @param string $marker
     * @param array $args
     * @return Page
     */
    public function setPage($marker, array $args = [])
    {
        $this->link = $this->c->Router->link($marker, $args);
        return $this;
    }

    /**
     * Задает ссылку для перехода
     * @param string $url
     * @return Page
     */
    public function setUrl($url)
    {
        $this->link = $url;
        return $this;
    }

    /**
     * Задает сообщение
     * @param string $message
     * @return Page
     */
    public function setMessage($message)
    {
        // переадресация без вывода сообщения
        if ($this->config['o_redirect_delay'] == '0') {
            return $this;
        }

        $this->nameTpl = 'layouts/redirect';
        $this->titles = [
            __('Redirecting'),
        ];
        $this->data = [
            'Message' => $message,
            'Timeout' => (int) $this->config['o_redirect_delay'],  //???? перенести в заголовки?
        ];
        return $this;
    }

    /**
     * Возвращает HTTP заголовки страницы
     * @return array
     */
    public function getHeaders()
    {
        // переадресация без вывода сообщения
        if (empty($this->data)) {
            $this->httpHeaders = [
                'Location: ' . $this->link, //????
            ];
        }
        return parent::getHeaders();
    }

    /**
     * Возвращает данные для шаблона
     * @return array
     */
    public function getData()
    {
        $this->data['Link'] = $this->link;
        return parent::getData();
    }
}
