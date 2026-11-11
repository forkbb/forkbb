<?php
/**
 * This file is part of the ForkBB <https://forkbb.org, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Provider\Driver;

use ForkBB\Models\Provider\Driver;
use RuntimeException;

class Discord extends Driver
{
    protected string $origName   = 'discord';
    protected string $authURL    = 'https://discord.com/oauth2/authorize';
    protected string $formAction = 'https://discord.com/oauth2/authorize';
    protected string $tokenURL   = 'https://discord.com/api/oauth2/token';
    protected string $userURL    = 'https://discord.com/api/users/@me';
    protected string $scope      = 'identify';

    /**
     * Запрашивает информацию о пользователе
     * Проверяет ответ
     * Запоминает данные пользователя
     */
    public function reqUserInfo(): bool
    {
        $this->userInfo = [];

        $options = [
            'header' => [
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
        return (string) ($this->userInfo['username'] ?? '');
    }

    /**
     * Возвращает имя пользователя (от провайдера)
     */
    protected function getuserName(): string
    {
        return '';
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
        if (
            empty($this->userInfo['avatar'])
            || ! \is_string($this->userInfo['avatar'])
        ) {
            return '';

        } else {
            return "https://cdn.discordapp.com/avatars/{$this->userId}/{$this->userInfo['avatar']}";
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
        return FORK_GEN_NOT;
    }
}
