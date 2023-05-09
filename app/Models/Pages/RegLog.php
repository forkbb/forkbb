<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User\User;
use function \ForkBB\__;

class RegLog extends Page
{
    /**
     * Обрабатывает нажатие одной из кнопок провайдеров
     */
    public function redirect(): Page
    {
        if (
            1 !== $this->c->config->b_oauth_allow
            || empty($list = $this->c->providers->active())
        ) {
            return $this->c->Message->message('Bad request');
        }

        $rules = [
            'token' => 'token:RegLogRedirect',
        ];

        foreach ($list as $name) {
            $rules[$name] = 'string';
        }

        $v = $this->c->Validator->reset()->addRules($rules);

        if (
            ! $v->validation($_POST)
            || 1 !== \count($form = $v->getData(false, ['token']))
        ) {
            return $this->c->Message->message('Bad request');
        }

        return $this->c->Redirect->url($this->c->providers->init()->get(\array_key_first($form))->linkAuth);
    }

    /**
     * Обрабатывает ответ сервера
     */
    public function callback(array $args): Page
    {
        if (
            1 !== $this->c->config->b_oauth_allow
            || empty($list = $this->c->providers->active())
            || empty($list[$args['name']])
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('admin_providers');

        $provider = $this->c->providers->init()->get($args['name']);
        $stages   = [1, 2, 3];

        foreach ($stages as $stage) {
            $result = match ($stage) {
                1 => $provider->verifyAuth($_GET),
                2 => $provider->reqAccessToken(),
                3 => $provider->reqUserInfo(),
            };

            if (true !== $result) {
                return $this->c->Message->message($provider->error);
            }
        }

        exit(var_dump($provider->userId, $provider->userName, $provider->userEmail));
    }
}
