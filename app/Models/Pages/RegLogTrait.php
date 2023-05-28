<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Models\Model;
use function \ForkBB\__;

trait RegLogTrait
{
    protected function reglogForm(string $type): array
    {
        if (
            1 !== $this->c->config->b_oauth_allow
            || empty($list = $this->c->providers->active())
        ) {
            return [];
        }

        $this->c->Lang->load('admin_providers');

        switch ($type) {
            case 'reg':
                $message = 'Sign up with %s';

                break;
            case 'add':
                $message = 'From %s';

                break;
            default:
                $message = 'Sign in with %s';

                break;
        }

        $btns = [];

        foreach ($list as $name) {
            $btns[$name] = [
                'type'  => 'submit',
                'value' => __([$message, __($name)]),
            ];
        }

        $args = ['type' => $type];

        return [
            'action' => $this->c->Router->link('RegLogRedirect', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('RegLogRedirect', $args),
            ],
            'sets'   => [],
            'btns'   => $btns,
        ];
    }
}
