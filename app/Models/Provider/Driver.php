<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Provider;

use ForkBB\Core\Container;
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
            'headers' => [
                'Accept: application/json',
                'Content-type: application/x-www-form-urlencoded',
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
        $result = null;

        //преобразовать массив переменных запроса в строку запроса
        if (\is_array($options['query'] ?? null)) {
            $options['query'] = \http_build_query($options['query']);
        }
        // дополнить url строкой запроса
        if (\is_string($options['query'] ?? null)) {
            $url .= (false === \strpos($url, '?') ? '?' : '&') . $options['query'];

            unset($options['query']);
        }

        if (\extension_loaded('curl')) {
            $result = $this->curlRequest($method, $url, $options);

        } elseif (\filter_var(\ini_get('allow_url_fopen'), \FILTER_VALIDATE_BOOL)) {
            $result = $this->streamRequest($method, $url, $options);
        }

        if (null === $result) {
            $this->error = 'No cURL and allow_url_fopen OFF';

            return false;

        } elseif (\is_string($result)) {
            if (\str_starts_with($this->respContentType, 'application/json')) {
                $data = \json_decode($result, true, 20);

                if (\is_array($data)) {
                    return $this->c->Secury->replInvalidChars($data);
                }

                $this->error ??= 'Bad json';
            }

            $this->error ??= "Bad Content-type: {$this->respContentType}";
        }

        return false;
    }

    /**
     * Отправляет/получает данные через cURL
     */
    protected function curlRequest(string $method, string $url, array $options): string|false
    {
        $ch = \curl_init($url);

        if (! $ch) {
            $this->error = "Failed cURL init for {$url}";

            return false;
        }

        switch ($method) {
            case 'POST':
                \curl_setopt($ch, \CURLOPT_POST, true);

                if (\is_array($options['form_params'] ?? null)) {
                    \curl_setopt($ch, \CURLOPT_POSTFIELDS, \http_build_query($options['form_params']));
                }

                break;
            default:
                \curl_setopt($ch, \CURLOPT_HTTPGET, true);

                break;
        }

        \curl_setopt($ch, \CURLOPT_PROTOCOLS, \CURLPROTO_HTTPS | \CURLPROTO_HTTP);
        \curl_setopt($ch, \CURLOPT_REDIR_PROTOCOLS, \CURLPROTO_HTTPS);
        \curl_setopt($ch, \CURLOPT_MAXREDIRS, 5);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, 10);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_HEADER, false);
        \curl_setopt($ch, \CURLOPT_USERAGENT, "ForkBB (Client ID: {$this->client_id})");

        if (\is_array($options['headers'] ?? null)) {
            \curl_setopt($ch, \CURLOPT_HTTPHEADER, $options['headers']);
        }

        $result = \curl_exec($ch);

        if (false === $result) {
            $this->error = 'cURL error: ' . \curl_error($ch);

        } else {
            $this->respContentType = \curl_getinfo($ch, \CURLINFO_CONTENT_TYPE);
            $this->respHttpCode    = \curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);
        }

//        \curl_close($ch);

        return $result;
    }

    /**
     * Отправляет/получает данные через file_get_contents()
     */
    protected function streamRequest(string $method, string $url, array $options): string|false
    {
        $http = [
            'max_redirects' => 10,
            'timeout'       => 10,
            'user_agent'    => "ForkBB (Client ID: {$this->client_id})",
        ];

        switch ($method) {
            case 'POST':
                $http['method'] = 'POST';

                if (\is_array($options['form_params'] ?? null)) {
                    $http['content'] = \http_build_query($options['form_params']);
                }

                break;
            default:
                $http['method'] = 'GET';

                break;
        }

        if (\is_array($options['headers'] ?? null)) {
            $http['header'] = $options['headers'];
        }

        $context = \stream_context_create(['http' => $http]);
        $result  = @\file_get_contents($url, false, $context);

        if (false === $result) {
            $this->error = "Failed file_get_contents for {$url}";

        } else {
            if (\function_exists('\\http_get_last_response_headers')) {
                $http_response_header = \http_get_last_response_headers();
            }

            $this->respContentType = $this->parseHeader($http_response_header, 'Content-Type:\s*(.+)');
            $this->respHttpCode    = (int) $this->parseHeader($http_response_header, 'HTTP/[0-9.]+\s+([0-9]+)');
        }

        return $result;
    }

    /**
     * Достает по шаблону значение нужного заголовка из списка
     */
    protected function parseHeader(array $headers, string $pattern): string
    {
        while ($header = \array_pop($headers)) {
            if (\preg_match('%^' . $pattern . '%i', $header, $matches)) {
                return \trim($matches[1]);
            }
        }

        return '';
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
