<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Provider;

use ForkBB\Core\Container;
use ForkBB\Models\Model;
use RuntimeException;

abstract class Driver extends Model
{
    const JSON_OPTIONS  = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;

    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Provider';

    protected string $origName;
    protected string $authURL;
    protected string $tokenURL;
    protected string $userURL;
    protected string $scope;

    public function __construct(protected string $client_id, protected string $client_secret, Container $c)
    {
        parent::__construct($c);

        $this->zDepend = [
            'code'         => ['access_token', 'userInfo', 'userId', 'userLogin', 'userName', 'userEmail', 'userEmailVerifed', 'userAvatar', 'userURL', 'userLocation', 'userGender'],
            'access_token' => ['userInfo', 'userId', 'userLogin', 'userName', 'userEmail', 'userEmailVerifed', 'userAvatar', 'userURL', 'userLocation', 'userGender'],
            'userInfo'     => ['userId', 'userLogin', 'userName', 'userEmail', 'userEmailVerifed', 'userAvatar', 'userURL', 'userLocation', 'userGender'],
        ];
    }

    /**
     * Проверяет и устанавливает имя провайдера
     */
    protected function setname(string $name):void
    {
        if ($this->origName !== $name) {
            throw new RuntimeException("Invalid name: {$name}");
        }

        $this->setAttr('name', $name);
    }

    /**
     * Формирует ссылку переадресации
     */
    protected function getlinkCallback(): string
    {
        return $this->c->Router->link('RegLogCallback', ['name' => $this->origName]);
    }

    /**
     * Возвращает client_id
     */
    protected function getclient_id(): string
    {
        return $this->client_id;
    }

    /**
     * Возвращает client_secret
     */
    protected function getclient_secret(): string
    {
        return $this->client_secret;
    }

    /**
     * Формирует ссылку авторизации на сервере провайдера
     */
    protected function getlinkAuth(): string
    {
        $params = [
            'response_type' => 'code',
            'scope'         => $this->scope,
            'state'         => $this->c->Csrf->createHash($this->origName, ['ip' => $this->c->user->ip]),
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->linkCallback,
        ];

        return $this->authURL . '?' . \http_build_query($params);
    }

    /**
     * Проверяет правильность state
     */
    protected function verifyState(string $state): bool
    {
        return $this->c->Csrf->verify($state, $this->origName, ['ip' => $this->c->user->ip]);
    }

    /**
     * Проверяет ответ сервера провайдера после авторизации пользователя
     * Запоминает code
     */
    public function verifyAuth(array $data): bool
    {
        $this->code = '';

        if (! \is_string($data['code'] ?? null)) {
            $error = $data['error_description'] ?? ($data['error'] ?? null);

            if (! \is_string($error)) {
                $error = 'undefined error';
            }

            $this->error = ['Provider response error: %s', $error];

            return false;
        }

        if (
            ! \is_string($data['state'] ?? null)
            || ! $this->verifyState($data['state'])
        ) {
            $this->error = 'State error';

            return false;
        }

        $this->code = $data['code'];

        return true;
    }

    /**
     * Запрашивает access token на основе code
     * Проверяет ответ
     * Запоминает access token
     */
    public function reqAccessToken(): bool
    {
        $this->access_token = '';

        $params = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'code'          => $this->code,
            'redirect_uri'  => $this->linkCallback,
        ];

        if (empty($ch = \curl_init($this->tokenURL))) {
            $this->error     = 'cURL error';
            $this->curlError = \curl_error($ch);

            return false;
        }

        \curl_setopt($ch, \CURLOPT_MAXREDIRS, 10);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, 10);
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, ['Accept: application/json']);
        \curl_setopt($ch, \CURLOPT_POST, true);
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, \http_build_query($params));
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_HEADER, false);

        $result = \curl_exec($ch);

        \curl_close($ch);

        if (false === $result) {
            $this->error     = 'cURL error';
            $this->curlError = \curl_error($ch);

            return false;
        }

        if (
            ! isset($result[1])
            || '{' !== $result[0]
            || '}' !== $result[-1]
            || ! \is_array($data = \json_decode($result, true, 20, self::JSON_OPTIONS))
            || ! isset($data['access_token'])
        ) {
            $error = $data['error_description'] ?? ($data['error'] ?? null);

            if (! \is_string($error)) {
                $error = 'undefined error';
            }

            $this->error = ['Token error: %s', $error];

            return false;
        }

        $this->access_token = $data['access_token'];

        return true;
    }

    /**
     * Запрашивает информацию о пользователе
     * Проверяет ответ
     * Запоминает данные пользователя
     */
    abstract public function reqUserInfo(): bool;

    /**
     * Возвращает идентификатор пользователя (от провайдера)
     */
    abstract protected function getuserId(): string;

    /**
     * Возвращает логин пользователя (от провайдера)
     */
    abstract protected function getuserLogin(): string;

    /**
     * Возвращает имя пользователя (от провайдера)
     */
    abstract protected function getuserName(): string;

    /**
     * Возвращает email пользователя (от провайдера)
     */
    abstract protected function getuserEmail(): string;

    /**
     * Возвращает флаг подлинности email пользователя (от провайдера)
     */
    abstract protected function getuserEmailVerifed(): bool;

    /**
     * Возвращает ссылку на аватарку пользователя (от провайдера)
     */
    abstract protected function getuserAvatar(): string;

    /**
     * Возвращает ссылку на профиль пользователя (от провайдера)
     */
    abstract protected function getuserURL(): string;

    /**
     * Возвращает местоположение пользователя (от провайдера)
     */
    abstract protected function getuserLocation(): string;

    /**
     * Возвращает пол пользователя (от провайдера)
     */
    abstract protected function getuserGender(): int;
}
