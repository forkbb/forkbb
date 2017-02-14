<?php

namespace ForkBB\Models\Pages;

class Ban extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'ban';

    /**
     * HTTP статус ответа для данной страницы
     * @var int
     */
    protected $httpStatus = 403;

    /**
     * Подготавливает данные для шаблона
     * @param array $banned
     * @return Page
     */
    public function ban(array $banned)
    {
        $this->titles = [
            __('Info'),
        ];
        if (! empty($banned['expire'])) {
             $banned['expire'] = strtolower($this->time($banned['expire'], true));
        }
        $this->data = [
            'banned' => $banned,
            'adminEmail' => $this->config['o_admin_email'],
        ];
        return $this;
    }
}
