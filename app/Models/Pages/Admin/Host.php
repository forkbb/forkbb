<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use function \ForkBB\__;

class Host extends Admin
{
    /**
     * Подготавливает данные для шаблона
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('admin_host');

        $ip = \filter_var($args['ip'], \FILTER_VALIDATE_IP);

        if (false === $ip) {
            return $this->c->Message->message('Bad request', false); // ??????
        }

        $host = @\gethostbyaddr($ip);

        $this->nameTpl = 'message';
        $this->titles  = __('Info');
        $this->back    = true;
        $this->fIswev  = [
            'i',
            __('Host info', $ip, $host, $this->c->Router->link(
                'AdminUsersResult',
                [
                    'data' => "ip:{$ip}",
                ]
            )),
        ];

        return $this;
    }
}
