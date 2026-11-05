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

class VKId extends Driver
{
    const VK_COOKIE = 'vkid_code_verifier';

    protected string $origName   = 'vkid';
    protected string $authURL    = 'https://id.vk.ru/authorize';
    protected string $formAction = 'https://id.vk.ru/authorize https://id.vk.ru/auth';
    protected string $tokenURL   = 'https://id.vk.ru/oauth2/auth';
    protected string $userURL    = 'https://id.vk.ru/oauth2/user_info';
    protected string $scope      = ''; // 'vkid.personal_info email'; // разрешает не передавать

    /**
     * Вычисляет code_challenge.
     */
    protected function codeChallengeS256(string $verifier): string
    {
        return \rtrim(\strtr(\base64_encode(\hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Возвращает параметры для ссылки авторизации
     */
    protected function linkAuthParams(string $type): array
    {
        $codeVerifier = $this->c->Secury->randomPass(64);

        $this->c->Cookie->set(self::VK_COOKIE, $codeVerifier);

        $params                          = parent::linkAuthParams($type);
        $params['code_challenge']        = $this->codeChallengeS256($codeVerifier);
        $params['code_challenge_method'] = 'S256';

        return $params;
    }

    /**
     * Возвращает данные формы для запроса токена
     */
    protected function reqAccessTokenParams(): array
    {
        $deviceId     = $this->rawAuthData['device_id'] ?? null;
        $codeVerifier = $this->c->Cookie->get(self::VK_COOKIE);
        $type         = $this->stateType;

        $this->c->Cookie->delete(self::VK_COOKIE);

        $params                  = parent::reqAccessTokenParams();
        $params['code_verifier'] = \is_string($codeVerifier) ? $codeVerifier : '';
        $params['state']         = $type . '_' . $this->c->Csrf->createHash($this->origName, ['ip' => $this->c->user->ip, 'type' => $type]);

        if (\is_string($deviceId)) {
            $params['device_id'] = $deviceId;
        }

        return $params;
    }

    /**
     * Запрашивает access token на основе code
     * Проверяет ответ
     * Запоминает access token
     * Запоминает необработанные данные ответа в rawTokenData
     */
    public function reqAccessToken(): bool
    {
        if (false === parent::reqAccessToken()) {
            return false;

        } elseif (
            ! \is_string($this->rawTokenData['state'] ?? null)
            || ! $this->verifyState($this->rawTokenData['state'])
        ) {
            $this->error = 'State error';

            return false;

        } else {
            return true;
        }
    }

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
            ],
            'form_params' => [
                'client_id'    => $this->client_id,
                'access_token' => $this->access_token,
            ],
        ];

        $response = $this->request('POST', $this->userURL, $options);

        if (! empty($response['user'])) {
            $this->userInfo = $response['user'];

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
        return (string) ($this->userInfo['user_id'] ?? '');
    }

    /**
     * Возвращает логин пользователя (от провайдера)
     */
    protected function getuserLogin(): string
    {
        return (string) ($this->userInfo['first_name'] ?? '');
    }

    /**
     * Возвращает имя пользователя (от провайдера)
     */
    protected function getuserName(): string
    {
        return (string) ($this->userInfo['first_name'] ?? '');
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
        return false; // ????
    }

    /**
     * Возвращает ссылку на аватарку пользователя (от провайдера)
     */
    protected function getuserAvatar(): string
    {
        return ''; // ????
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
        return match ($this->userInfo['sex'] ?? 0) {
            2       => FORK_GEN_MAN,
            1       => FORK_GEN_FEM,
            default => FORK_GEN_NOT,
        };
    }
}
