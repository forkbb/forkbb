<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;

class Message extends Page
{
    /**
     * Подготавливает данные для шаблона
     *
     * @param string $message
     * @param bool $back
     * @param int $status
     *
     * @return Page
     */
    public function message($message, $back = true, $status = 404, array $headers = [])
    {
        $this->nameTpl     = 'message';
        $this->httpStatus  = $status;
        $this->httpHeaders = $headers;
        $this->titles      = \ForkBB\__('Info');
        $this->message     = \ForkBB\__($message);
        $this->back        = $back;

        return $this;
    }
}
