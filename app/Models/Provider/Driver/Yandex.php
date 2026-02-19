<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Provider\Driver;

use ForkBB\Models\Provider\Driver;
use RuntimeException;

class Yandex extends Driver
{
    protected string $origName   = 'yandex';
    protected string $authURL    = 'https://oauth.yandex.ru/authorize';
    protected string $formAction = 'https://oauth.yandex.ru/authorize https://passport.yandex.ru';
    protected string $tokenURL   = 'https://oauth.yandex.ru/token';
    protected string $userURL    = 'https://login.yandex.ru/info?format=json';
    protected string $scope      = ''; // 'login:info login:email login:avatar'; // разрешает не передавать

    /**
     * Запрашивает информацию о пользователе
     * Проверяет ответ
     * Запоминает данные пользователя
     */
    public function reqUserInfo(): bool
    {
        $this->userInfo = [];

        $options = [
            'headers' => [
                'Accept: application/json',
                "Authorization: OAuth {$this->access_token}",
            ],
        ];

        $response = $this->request('GET', $this->userURL, $options);

        if (! empty($response['id'])) {
            $this->userInfo = $response;

            return true;

        } elseif (\is_array($response)) {
            $this->error = 'User error';
        }

        return false;
    }

    /**
     * Возвращает идентификатор пользователя (от провайдера)
     */
    protected function getuserId(): string
    {
        return (string) ($this->userInfo['id'] ?? '');
    }

    /**
     * Возвращает логин пользователя (от провайдера)
     */
    protected function getuserLogin(): string
    {
        return (string) ($this->userInfo['login'] ?? '');
    }

    /**
     * Возвращает имя пользователя (от провайдера)
     */
    protected function getuserName(): string
    {
        return (string) ($this->userInfo['real_name'] ?? ''); // ???? or display_name?
    }

    /**
     * Возвращает email пользователя (от провайдера)
     */
    protected function getuserEmail(): string
    {
        return $this->c->Mail->valid($this->userInfo['default_email'] ?? null) ?: "{$this->origName}-{$this->userId}@localhost";
    }

    /**
     * Возвращает флаг подлинности email пользователя (от провайдера)
     */
    protected function getuserEmailVerifed(): bool
    {
        return false; // ????
    }

    /**
     * Возвращает ссылку на аватарку пользователя (от провайдера)
     */
    protected function getuserAvatar(): string
    {
        if (
            empty($this->userInfo['is_avatar_empty'])
            && ! empty($this->userInfo['default_avatar_id'])
        ) {
            return "https://avatars.yandex.net/get-yapic/{$this->userInfo['default_avatar_id']}/islands-200";

        } else {
            return '';
        }
    }

    /**
     * Возвращает ссылку на профиль пользователя (от провайдера)
     */
    protected function getuserURL(): string
    {
        return '';
    }

    /**
     * Возвращает местоположение пользователя (от провайдера)
     */
    protected function getuserLocation(): string
    {
        return '';
    }

    /**
     * Возвращает пол пользователя (от провайдера)
     */
    protected function getuserGender(): int
    {
        if (isset($this->userInfo['sex'])) {
            if ('male' === $this->userInfo['sex']) {
                return FORK_GEN_MAN;

            } elseif ('female' === $this->userInfo['sex']) {
                return FORK_GEN_FEM;
            }
        }

        return FORK_GEN_NOT;
    }
}
