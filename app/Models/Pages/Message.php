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
     * @param array $headers
     *
     * @return Page
     */
    public function message(string $message, bool $back = true, int $status = 404, array $headers = []): Page
    {
        $this->nameTpl     = 'message';
        $this->httpStatus  = \max(200, $status);
        $this->httpHeaders = $headers;
        $this->titles      = \ForkBB\__('Info');
        $this->back        = $back;


        if ($status < 200) {
            $type = 'i';
        } elseif ($status < 300) {
            $type = 's';
        } elseif ($status < 400) {
            $type = 'w';
        } else {
            $type = 'e';
        }
        $this->fIswev = [$type, \ForkBB\__($message)];

        return $this;
    }
}
