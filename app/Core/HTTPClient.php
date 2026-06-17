<?php
/**
 * This file is part of the ForkBB <https://forkbb.org, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

class HTTPClient
{
    const OFF    = 0;
    const CURL   = 1;
    const STREAM = 2;

    /**
     * 'base_uri'        string       Базовый uri
     * 'user_agent'      string       Строка агента
     * 'follow_location' int          Флаг использования перенаправлений
     * 'max_redirects'   int          Максимальное количество перенаправлений
     * 'timeout'         float        Время ожидания на чтение в секундах
     * 'query'           array|string Переменные дописываемые к uri
     * 'form_params'     array        Данные для POST application/x-www-form-urlencoded
     * 'json'            array        Данные для POST application/json
     * 'content'         string       Тело POST запроса
     * 'header'          array        HTTP заголовки запроса
     * 'sink'            resource     Указатель на файл в который записывается ответ
     */

    protected string $baseUri = '';

    protected array $default = [
        'user_agent'      => 'ForkBB engine',
        'follow_location' => 1,
        'max_redirects'   => 5,
        'timeout'         => 10.0,
    ];

    public function __construct(array $default = [])
    {
        if (isset($default['base_uri'])) {
            $this->baseUri = $default['base_uri'];

            unset($default['base_uri']);
        }

        $this->default = \array_replace($this->default, $default);
    }

    /**
     * Возвращает доступность запросов
     */
    public static function status(): int
    {
        return match (true) {
            \extension_loaded('curl')                                       => self::CURL,
            \filter_var(\ini_get('allow_url_fopen'), \FILTER_VALIDATE_BOOL) => self::STREAM,
            default                                                         => self::OFF
        };
    }

    /**
     * Делает GET запрос
     */
    public function get(string $uri, array $options = [])
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * Делает POST запрос
     */
    public function post(string $uri, array $options = [])
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * Делает запрос
     */
    public function request(string $method, string $uri, array $options = []): ?array
    {
        $status = self::status();

        if (self::OFF === $status) {
            return null;
        }

        $options += $this->default;

        if (\str_starts_with($uri, '//')) {
            $uri = 'https:' . $uri;

        } elseif (
            '' !== $this->baseUri
            && ! \str_starts_with($uri, 'http://')
            && ! \str_starts_with($uri, 'https://')
        ) {
            if ('' === $uri) {
                $uri = $this->baseUri;

            } elseif ('/' === $uri[0]) {
                \preg_match('%^https?://[^#?&/]+%', $this->baseUri, $matches);

                $uri = $matches[0] . $uri;

            } elseif ('?' === $uri[0]) {
                \preg_match('%^https?://[^#?&]+%', $this->baseUri, $matches);

                $uri = $matches[0] . $uri;

            } else {
                \preg_match('%^https?://[^#?&/]+(?:[^#?&/]*/)*%', $this->baseUri, $matches);

                $uri = \rtrim($matches[0], '/') . '/' . $uri;
            }
        }

        if (isset($options['query'])) {
            if (\is_array($options['query'])) {
                $options['query'] = \http_build_query($options['query'], '', '&', \PHP_QUERY_RFC1738);
            }

            if (\is_string($options['query'])) {
                $uri .= (false === \strpos($uri, '?') ? '?' : '&') . $options['query'];
            }

            unset($options['query']);
        }

        if (isset($options['form_params'])) {
            $options['content'] = \http_build_query($options['form_params'], '', '&', \PHP_QUERY_RFC1738);
            // ????
            $options['header'][] = 'Content-Type: application/x-www-form-urlencoded';

            unset($options['form_params']);
        }

        if (isset($options['json'])) {
            $options['content'] = \json_encode($options['json']);
            // ????
            $options['header'][] = 'Content-Type: application/json';

            unset($options['json']);
        }

        if (self::CURL === $status) {
            $result = $this->curlRequest($method, $uri, $options);

        } elseif (self::STREAM === $status) {
            $result = $this->streamRequest($method, $uri, $options);
        }

        if (
            \is_string($result['response'] ?? null)
            && \str_starts_with($result['contentType'], 'application/json')
        ) {
            $result['json'] = \json_decode($result['response'], true, 128);
        }

        return $result;
    }

    protected function curlRequest(string $method, string $url, array $options): array
    {
        $ch = \curl_init($url);

        if (! $ch) {
            return ['error' => "Failed cURL init for {$url}"];
        }

        switch ($method) {
            case 'POST':
                \curl_setopt($ch, \CURLOPT_POST, true);

                if (isset($options['content'])) {
                    \curl_setopt($ch, \CURLOPT_POSTFIELDS, $options['content']);
                }

                break;
            default:
                \curl_setopt($ch, \CURLOPT_HTTPGET, true);

                break;
        }

        \curl_setopt($ch, \CURLOPT_PROTOCOLS, \CURLPROTO_HTTPS | \CURLPROTO_HTTP);
        \curl_setopt($ch, \CURLOPT_REDIR_PROTOCOLS, \CURLPROTO_HTTPS);
        \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, 1 === $options['follow_location']);
        \curl_setopt($ch, \CURLOPT_MAXREDIRS, $options['max_redirects']);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, $options['timeout']);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_HEADER, false);
        \curl_setopt($ch, \CURLOPT_USERAGENT, $options['user_agent']);

        if (! empty($options['header'])) {
            \curl_setopt($ch, \CURLOPT_HTTPHEADER, $options['header']);
        }

        if (\is_resource($options['sink'] ?? null)) {
            \curl_setopt($ch, \CURLOPT_FILE, $options['sink']);
        }

        $result = \curl_exec($ch);

        if (false === $result) {
            return ['error' => 'cURL error: ' . \curl_error($ch)];

        } else {
            return [
                'httpCode'    => \curl_getinfo($ch, \CURLINFO_RESPONSE_CODE),
                'contentType' => \curl_getinfo($ch, \CURLINFO_CONTENT_TYPE),
                'response'    => $result,
            ];
        }
    }

    protected function streamRequest(string $method, string $url, array $options): array
    {
        $options['method'] = $method;
        $context           = \stream_context_create(['http' => $options]);

        if (\is_resource($options['sink'] ?? null)) {
            $fh = \fopen($url, 'rb' , false, $context);

            if (! $fh) {
                return ['error' => "Failed fopen() for {$url}"];
            }

            while (! \feof($fh)) {
                if (false === ($buffer = \fread($fh, 4096))) {
                    return ['error' => "Failed fread() from {$url}"];
                }

                if (false === \fwrite($options['sink'], $buffer)) {
                    return ['error' => "Failed fwrite() to temp file"];
                }
            }

            \fclose($fh);

            $result = true;

        } else {
            $result = \file_get_contents($url, false, $context);

            if (false === $result) {
                return ['error' => "Failed file_get_contents for {$url}"];
            }
        }

        if (\function_exists('\\http_get_last_response_headers')) {
            $http_response_header = \http_get_last_response_headers();
        }

        return [
            'httpCode'    => (int) $this->parseHeader('HTTP/[0-9.]+\s+([0-9]+)', $http_response_header),
            'contentType' => $this->parseHeader('Content-Type:\s*(.+)'),
            'response'    => $result,
        ];
    }

    protected array $streamHeaders;

    /**
     * Достает по шаблону значение нужного заголовка из списка
     */
    protected function parseHeader(string $pattern, ?array $headers = null): string
    {
        $result = '';

        if (null === $headers) {
            $fill    = false;
            $headers = $this->streamHeaders;

        } else {
            $fill                = true;
            $this->streamHeaders = [];
        }

        while ($header = \array_pop($headers)) {
            if (true === $fill) {
                $this->streamHeaders[] = $header;
            }

            if (\preg_match('%^' . $pattern . '%i', $header, $matches)) {
                $result = \trim($matches[1]);

                break;
            }
        }

        if (true === $fill) {
            $this->streamHeaders = \array_reverse($this->streamHeaders);
        }

        return $result;
    }
}
