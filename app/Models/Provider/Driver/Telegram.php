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

class Telegram extends Driver
{
    protected bool $usePKCE = true;

    protected string $origName   = 'telegram';
    protected string $authURL    = 'https://oauth.telegram.org/auth';
    protected string $formAction = 'https://oauth.telegram.org/auth';
    protected string $tokenURL   = 'https://oauth.telegram.org/token';
    protected string $userURL    = '';
    protected string $scope      = 'openid profile';

    /**
     * Возвращает список заголовков для запроса токена
     */
    protected function reqAccessTokenHeader(): array
    {
        $header      = parent::reqAccessTokenHeader();
        $credentials = \base64_encode($this->client_id . ':' . $this->client_secret);
        $header[]    = "Authorization: Basic {$credentials}";

        return $header;
    }

    /**
     * Запрашивает информацию о пользователе
     * Проверяет ответ
     * Запоминает данные пользователя
     */
    public function reqUserInfo(): bool
    {
        $this->userInfo = [];

        $response = $this->decodeJWT($this->rawTokenData['id_token'] ?? '');

        if (! empty($response['sub'])) {
            $this->userInfo = $response;

            return true;

        } else {
            $this->error = 'User error';

            return false;
        }
    }

    /**
     * Возвращает идентификатор пользователя (от провайдера)
     */
    protected function getuserId(): string
    {
        return (string) $this->userInfo['sub'];
    }

    /**
     * Возвращает логин пользователя (от провайдера)
     */
    protected function getuserLogin(): string
    {
        return (string) ($this->userInfo['preferred_username'] ?? $this->userInfo['name'] ?? '');
    }

    /**
     * Возвращает имя пользователя (от провайдера)
     */
    protected function getuserName(): string
    {
        return (string) ($this->userInfo['given_name'] ?? '');
    }

    /**
     * Возвращает email пользователя (от провайдера)
     */
    protected function getuserEmail(): string
    {
        return "{$this->origName}-{$this->userId}@localhost";
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
        return FORK_GEN_NOT;
    }
}
