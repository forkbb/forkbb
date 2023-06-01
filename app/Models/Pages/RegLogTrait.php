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
use ForkBB\Models\Provider\Driver;
use function \ForkBB\__;

trait RegLogTrait
{
    /**
     * Подготавливает массив данных для формы
     */
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

    /**
     * Кодирует данные провайдера(пользователя) в строку
     */
    protected function providerToString(Driver $provider): string
    {
        $data = [
            'name'     => $provider->name,
            'userInfo' => $provider->userInfo,
        ];

        $data = \base64_encode(\json_encode($data, FORK_JSON_ENCODE));
        $hash = $this->c->Secury->hash($data);

        return "{$data}:{$hash}";
    }

    /**
     * Раскодирует данные провайдера(пользователя) из строку или false
     */
    protected function stringToProvider(string $data): Driver|false
    {
        $data = \explode(':', $data);

        if (2 !== \count($data)) {
            return false;
        }

        if (
            ! \hash_equals($data[1], $this->c->Secury->hash($data[0]))
            || ! \is_array($data = \json_decode(\base64_decode($data[0], true), true))
        ) {
            return false;
        }

        $provider           = $this->c->providers->init()->get($data['name']);
        $provider->userInfo = $data['userInfo'];

        return $provider;
    }

    /**
     * Подбирает уникальное имя для регистрации пользователя
     */
    protected function nameGenerator(Driver $provider): string
    {
        $names = [];

        if ('' != $provider->userName) {
            $names[] = $provider->userName;
        }

        if ('' != $provider->userLogin) {
            $names[] = $provider->userLogin;
        }

        if ('' != ($tmp = (string) \strstr($provider->userEmail, '@', true))) {
            $names[] = $tmp;
        }

        $v    = (clone $this->c->Validator)->reset()->addRules(['name' => 'required|string:trim|username|noURL:1']);
        $end  = '';
        $i    = 0;

        while ($i < 3) {
            foreach ($names as $name) {
                if ($v->validation(['name' => $name . $end])) {
                    return $v->name;
                }
            }

            $end = '_' . $this->c->Secury->randomHash(4);
            ++$i;
        }

        return 'user' . \time();
    }
}
