<?php

namespace ForkBB\Models\Pages;

class Message extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'message';

    /**
     * Подготавливает данные для шаблона
     * @param string $message
     * @param bool $back
     * @param int $status
     * @return Page
     */
    public function message($message, $back = true, $status = 404, array $headers = [])
    {
        $this->httpStatus = $status;
        $this->httpHeaders = $headers;
        $this->titles = [
            __('Info'),
        ];
        $this->data = [
            'message' => __($message),
            'back' => $back,
        ];
        return $this;
    }
}
