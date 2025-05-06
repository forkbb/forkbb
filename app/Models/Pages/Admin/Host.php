<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

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
            return $this->c->Message->message('Bad request', false);
        }

        $this->nameTpl = 'message';
        $this->titles  = 'Info';
        $this->back    = true;
        $this->fIswev  = [
            FORK_MESS_INFO,
            [
                'Host info',
                $ip,
                $this->c->Router->link(
                    'AdminUsersResult',
                    [
                        'data' => "ip:{$ip}",
                    ]
                )
            ],
        ];

        $this->pageHeader('ipinfo', 'script', 10, [
            'src' => $this->publicLink('/js/ipinfo.js'),
        ]);
        $this->addRulesToCSP(['connect-src' => 'https://ip.guide/']);

        return $this;
    }
}
