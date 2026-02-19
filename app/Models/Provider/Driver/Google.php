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

class Google extends Driver    // Not tested. Google banned the registration of new applications for my country.
{
    protected string $origName   = 'google';
    protected string $authURL    = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected string $formAction = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected string $tokenURL   = 'https://oauth2.googleapis.com/token';
    protected string $userURL    = 'https://www.googleapis.com/oauth2/v2/userinfo';
    protected string $scope      = 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile';

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

        if (
            ! empty($response['sub'])
            || ! empty($response['id'])
        ) {
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
        return (string) ($this->userInfo['sub'] ?? ($this->userInfo['id'] ?? '')); // ????
    }

    /**
     * Возвращает логин пользователя (от провайдера)
     */
    protected function getuserLogin(): string
    {
        return (string) ($this->userInfo['given_name'] ?? '');
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
        return ! empty($this->userInfo['verified_email'])
            && ! empty($this->userInfo['email'])
            && $this->userEmail === $this->userInfo['email'];
    }

    /**
     * Возвращает ссылку на аватарку пользователя (от провайдера)
     */
    protected function getuserAvatar(): string
    {
        return (string) ($this->userInfo['picture'] ?? '');
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
        if (isset($this->userInfo['gender'])) {
            if ('male' === $this->userInfo['gender']) {
                return FORK_GEN_MAN;

            } elseif ('female' === $this->userInfo['gender']) {
                return FORK_GEN_FEM;
            }
        }

        return FORK_GEN_NOT;
    }
}
