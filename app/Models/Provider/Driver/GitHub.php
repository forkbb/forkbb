<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Provider\Driver;

use ForkBB\Models\Provider\Driver;
use RuntimeException;

class GitHub extends Driver
{
    protected string $origName = 'github';
    protected string $authURL  = 'https://github.com/login/oauth/authorize';
    protected string $tokenURL = 'https://github.com/login/oauth/access_token';
    protected string $userURL  = 'https://api.github.com/user';
    protected string $scope    = 'read:user';

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
                "Authorization: Bearer {$this->access_token}",
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
        return (string) ($this->userInfo['name'] ?? '');
    }

    /**
     * Возвращает email пользователя (от провайдера)
     */
    protected function getuserEmail(): string
    {
        return $this->c->Mail->valid($this->userInfo['email'] ?? null) ?: "{$this->origName}-{$this->userId}@localhost";
    }

    /**
     * Возвращает флаг подлинности email пользователя (от провайдера)
     */
    protected function getuserEmailVerifed(): bool
    {
        return false;
    }

    /**
     * Возвращает ссылку на аватарку пользователя (от провайдера)
     */
    protected function getuserAvatar(): string
    {
        return (string) ($this->userInfo['avatar_url'] ?? '');
    }

    /**
     * Возвращает ссылку на профиль пользователя (от провайдера)
     */
    protected function getuserURL(): string
    {
        return $this->userInfo['html_url'];
    }

    /**
     * Возвращает местоположение пользователя (от провайдера)
     */
    protected function getuserLocation(): string
    {
        return \mb_substr((string) ($this->userInfo['location'] ?? ''),  0, 30, 'UTF-8');
    }

    /**
     * Возвращает пол пользователя (от провайдера)
     */
    protected function getuserGender(): int
    {
        return FORK_GEN_NOT;
    }
}
