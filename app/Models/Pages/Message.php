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
