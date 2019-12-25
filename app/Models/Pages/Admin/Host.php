<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Pages\Admin;

class Host extends Admin
{
    /**
     * Подготавливает данные для шаблона
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
        $this->c->Lang->load('admin_host');

        $ip = \filter_var($args['ip'], \FILTER_VALIDATE_IP);

        if (false === $ip) {
            return $this->c->Message->message('Bad request', false); // ??????
        }

        $host = @\gethostbyaddr($ip);

        $this->nameTpl = 'message';
        $this->titles  = \ForkBB\__('Info');
        $this->back    = true;
        $this->fIswev  = [
            'i',
            \ForkBB\__('Host info', $ip, $host, $this->c->Router->link('AdminUsersResult', ['data' => "ip:{$ip}"])),
        ];

        return $this;
    }
}
