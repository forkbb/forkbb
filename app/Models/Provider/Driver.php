<?php
/**
 * This file is part of the ForkBB <https://forkbb.org, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Provider;

use ForkBB\Core\Container;
use ForkBB\Core\HTTPClient;
use ForkBB\Models\Model;
use InvalidArgumentException;
use RuntimeException;

abstract class Driver extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Provider';

    protected string $origName;
    protected string $authURL;
    protected string $formAction;
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

        $this->setModelAttr('name', $name);
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
    public function linkAuth(string $type): string
    {
        if ('' == $type) {
            throw new InvalidArgumentException('Expected non-empty type');

        } elseif (0 !== \preg_match('%[^a-zA-Z]%', $type)) {
            throw new InvalidArgumentException('Invalid characters in type');
        }

        $params = [
            'response_type' => 'code',
            'scope'         => $this->scope,
            'state'         => $type . '_' . $this->c->Csrf->createHash($this->origName, ['ip' => $this->c->user->ip, 'type' => $type]),
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->linkCallback,
        ];

        return $this->authURL . '?' . \http_build_query(\array_filter($params));
    }

    /**
     * Проверяет правильность state
     * Запоминает stateType
     */
    protected function verifyState(string $state): bool
    {
        $state = \explode('_', $state, 2);

        if (2 !== \count($state)) {
            return false;
        }

        $this->stateType = $state[0];

        return $this->c->Csrf->verify($state[1], $this->origName, ['ip' => $this->c->user->ip, 'type' => $state[0]]);
    }

    /**
     * Проверяет ответ сервера провайдера после авторизации пользователя
     * Запоминает code
     */
    public function verifyAuth(): bool
    {
        $data       = $this->c->Secury->replInvalidChars($_GET);
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

        $options = [
            'header' => [
                'Accept' => 'application/json',
            ],
            'form_params' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'code'          => $this->code,
                'redirect_uri'  => $this->linkCallback,
            ],
        ];

        $response = $this->request('POST', $this->tokenURL, $options);

        if (isset($response['access_token'])) {
            $this->access_token = $response['access_token'];

            return true;

        } elseif (\is_array($response)) {
            $error = $response['error_description'] ?? ($response['error'] ?? null);

            if (! \is_string($error)) {
                $error = 'undefined error';
            }

            $this->error = ['Token error: %s', $error];
        }

        return false;
    }

    /**
     * Обменивается данными c сервером OAuth
     * Ответ пытается преобразовать в массив
     */
    protected function request(string $method, string $url, array $options): array|false
    {
        $options['user_agent'] = "ForkBB (Client ID: {$this->client_id})";

        $result = $this->httpClient->request($method, $url, $options);

        if (null === $result) {
            $this->error = 'No cURL and allow_url_fopen OFF';

        } elseif (! empty($result['error'])) {
            $this->error = $result['error'];

        } elseif (! isset($result['json'])) {
            $this->error = 'Bad Content-type: ' . $result['contentType'];

        } elseif (! \is_array($result['json'])) {
            $this->error = 'Bad json';

        } else {
            return $this->c->Secury->replInvalidChars($result['json']);
        }

        return false;
    }

    /**
     * Возвращает ссылку или ссылки через пробел для директивы form-action в CSP
     */
    protected function getformAction(): string
    {
        return $this->formAction;
    }

    protected function gethttpClient(): HTTPClient
    {
        return new HTTPClient();
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
