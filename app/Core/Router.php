<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Csrf;
use InvalidArgumentException;

class Router
{
    const OK = 200;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const NOT_IMPLEMENTED = 501;

    const DUO = ['GET', 'POST'];
    const GET = 'GET';
    const PST = 'POST';

    /**
     * Массив постоянных маршрутов
     */
    protected array $statical = [];

    /**
     * Массив динамических маршрутов
     */
    protected array $dynamic = [];

    /**
     * Список методов доступа
     */
    protected array $methods = [];

    /**
     * Массив для построения ссылок
     */
    protected array $links = [];

    /**
     * Host сайта
     */
    protected string $host;

    /**
     * Префикс uri
     */
    protected string $prefix;

    /**
     * Длина префикса в байтах
     */
    protected int $length;

    protected array $subSearch = [
        '/',
        '\\',
    ];
    protected array $subRepl = [
        '(_slash_)',
        '(_backslash_)',
    ];

    public function __construct(protected string $baseUrl, protected Csrf $csrf)
    {
        $this->host   = \parse_url($baseUrl, \PHP_URL_HOST);
        $this->prefix = \parse_url($baseUrl, \PHP_URL_PATH) ?? '';
        $this->length = \strlen($this->prefix);
    }

    /**
     * Проверка url на принадлежность форуму
     */
    public function validate(mixed $url, string $defMarker, array $defArgs = []): string
    {
        if (
            \is_string($url)
            && \parse_url($url, \PHP_URL_HOST) === $this->host
            && \is_string($path = \parse_url($url, \PHP_URL_PATH))
            && ($uri = \rawurldecode($path))                        // $path всегда начинается с наклонной черты
            && ($route = $this->route(self::GET, $uri))
            && (
                self::OK === $route[0]
                || (
                    self::METHOD_NOT_ALLOWED === $route[0]
                    && ($route = $this->route(self::PST, $uri))
                    && self::OK === $route[0]
                )
            )
        ) {
            if (isset($route[3])) {
                return $this->link($route[3], $route[2]);
            } else {
                return $url;
            }
        } else {
            return $this->link($defMarker, $defArgs);
        }
    }

    /**
     * Возвращает ссылку на основании маркера
     */
    public function link(string $marker = null, array $args = []): string
    {
        $result = $this->baseUrl;
        $anchor = isset($args['#']) ? '#' . \rawurlencode($args['#']) : '';

        // маркер пустой
        if (null === $marker) {
            return $result . "/{$anchor}";
        // такой ссылки нет
        } elseif (! isset($this->links[$marker])) {
            return $result . '/';
        // ссылка статична
        } elseif (\is_string($data = $this->links[$marker])) {
            return $result . $data . $anchor;
        }

        list($link, $names) = $data;
        // автоматическое вычисление токена/хэша
        if (
            isset($names['hash'])
            && ! isset($args['hash'])
        ) {
            $args['hash'] = $this->csrf->createHash($marker, $args);
        }
        if (
            isset($names['token'])
            && ! isset($args['token'])
        ) {
            $args['token'] = $this->csrf->create($marker, $args);
        }

        $data = [];
        // перечисление имен переменных для построения ссылки
        foreach ($names as $name => $need) {
            // значение есть
            if (isset($args[$name])) {
                // кроме page = 1
                if (
                    'page' !== $name
                    || 1 !== $args[$name]
                ) {
                    $data['{' . $name . '}'] = \rawurlencode(\str_replace($this->subSearch, $this->subRepl, (string) $args[$name]));

                    continue;
                }
            }

            // значения нет, но оно обязательно
            if ($need) {
                return $result . '/';
            // значение не обязательно
            } else {
//                $link = preg_replace('%\[[^\[\]{}]*{' . preg_quote($name, '%') . '}[^\[\]{}]*\]%', '', $link);
                $link = \preg_replace(
                    '%\[[^\[\]]*?{' . \preg_quote($name, '%') . '}[^\[\]]*+(\[((?>[^\[\]]*+)|(?1))+\])*?\]%',
                    '',
                    $link
                );
            }
        }
        $link = \str_replace(['[', ']'], '', $link);

        return $result . \strtr($link, $data) . $anchor;
    }

    /**
     * Метод определяет маршрут
     */
    public function route(string $method, string $uri): array
    {
        $head = 'HEAD' == $method;

        if (
            empty($this->methods[$method])
            && (
                ! $head
                || empty($this->methods['GET'])
            )
        ) {
            return [
                self::NOT_IMPLEMENTED,
            ];
        }

        if ($this->length) {
            if (0 === \strpos($uri, $this->prefix)) {
                $uri = \substr($uri, $this->length);
            } else {
                return [
                    self::NOT_FOUND,
                ];
            }
        }

        $allowed = [];

        if (isset($this->statical[$uri])) {
            if (isset($this->statical[$uri][$method])) {
                list($handler, $marker) = $this->statical[$uri][$method];

                return [
                    self::OK,
                    $handler,
                    [],
                    $marker,
                ];
            } elseif (
                $head
                && isset($this->statical[$uri]['GET'])
            ) {
                list($handler, $marker) = $this->statical[$uri]['GET'];

                return [
                    self::OK,
                    $handler,
                    [],
                    $marker,
                ];
            } else {
                $allowed = \array_keys($this->statical[$uri]);
            }
        }

        $pos  = \strpos($uri, '/', 1);
        $base = false === $pos ? $uri : \substr($uri, 0, $pos);

        if (isset($this->dynamic[$base])) {
            foreach ($this->dynamic[$base] as $pattern => $data) {
                if (! \preg_match($pattern, $uri, $matches)) {
                    continue;
                }

                if (isset($data[$method])) {
                    list($handler, $keys, $marker) = $data[$method];
                } elseif (
                    $head
                    && isset($data['GET'])
                ) {
                    list($handler, $keys, $marker) = $data['GET'];
                } else {
                    $allowed += \array_keys($data);

                    continue;
                }

                $args = [];
                foreach ($keys as $key => $type) {
                    if (isset($matches[$key][0])) {
                        $args[$key] = \str_replace($this->subRepl, $this->subSearch, $matches[$key]);

                        switch ($type) {
                            case 'i':
                                $args[$key] = (int) $args[$key]; // ???? добавить проверку типа?
                                break;
                        }
                    } else {
                        $args[$key] = null;
                    }
                }

                return [
                    self::OK,
                    $handler,
                    $args,
                    $marker,
                ];
            }
        }
        if (empty($allowed)) {
            return [
                self::NOT_FOUND,
            ];
        } else {
            return [
                self::METHOD_NOT_ALLOWED,
                $allowed,
            ];
        }
    }

    /**
     * Метод добавляет маршрут
     */
    public function add(array|string $method, string $route, string $handler, string $marker = null): void
    {
        if (\is_array($method)) {
            foreach ($method as $m) {
                $this->methods[$m] = 1;
            }
        } else {
            $this->methods[$method] = 1;
        }

        $link = $route;
        $pos  = \strpos($route, '#');

        if (
            false !== $pos
            && false === \strpos($route, ']', $pos)
        ) {
            $anchor = \substr($route, $pos);
            $route  = \substr($route, 0, $pos);
        } else {
            $anchor = '';
        }

        if (false === \strpbrk($route, '{}[]')) {
            $data = null;
            if (\is_array($method)) {
                foreach ($method as $m) {
                    $this->statical[$route][$m] = [$handler, $marker];
                }
            } else {
                $this->statical[$route][$method] = [$handler, $marker];
            }
        } else {
            $data = $this->parse($route);
            if (null === $data) {
                throw new InvalidArgumentException("Wrong route: {$route}");
            }
            if (\is_array($method)) {
                foreach ($method as $m) {
                    $this->dynamic[$data[0]][$data[1]][$m] = [$handler, $data[2], $marker];
                }
            } else {
                $this->dynamic[$data[0]][$data[1]][$method] = [$handler, $data[2], $marker];
            }
        }

        if ($marker) {
            if ($data) {
                $this->links[$marker] = [$data[3] . $anchor, $data[4]];
            } else {
                $this->links[$marker] = $link;
            }
        }
    }

    /**
     * Метод разбирает динамический маршрут
     */
    protected function parse(string $route): ?array
    {
        $parts = \preg_split('%([\[\]{}/#])%', $route, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);

        $s = 1;
        $base = $parts[0];
        if ('/' === $parts[0]) {
            $s     = 2;
            $base .= $parts[1];
        }
        if (
            isset($parts[$s])
            && '/' !== $parts[$s]
            && '[' !== $parts[$s]
        ) {
            $base = '/';
        }

        $pattern = '%^';
        $var     = false;
        $first   = false;
        $buffer  = '';
        $args    = [];
        $s       = 0;
        $req     = true;
        $argReq  = [];
        $temp    = '';

        foreach ($parts as $part) {
            if ($var) {
                switch ($part) {
                    case '{':
                        return null;
                    case '}':
                        if (! \preg_match('%^([a-zA-Z][^:|]*+)(?:\|([a-z]))?(?::(.+))?$%D', $buffer, $data)) {
                            return null;
                        }
                        $pattern          .= '(?P<' . $data[1] . '>' . ($data[3] ?? '[^/\x00-\x1f]+') . ')';
                        $args[$data[1]]    = empty($data[2]) ? 's' : $data[2];
                        $temp             .= '{' . $data[1] . '}';
                        $var               = false;
                        $buffer            = '';
                        $argsReq[$data[1]] = $req;
                        break;
                    default:
                        $buffer .= $part;
                }
            } elseif ($first) {
                switch ($part) {
                    case '/':
                    case '#':
                        $first    = false;
                        $pattern .= \preg_quote($part, '%');
                        $temp    .= $part;
                        break;
                    default:
                        return null;
                }
            } else {
                switch ($part) {
                    case '[':
                        ++$s;
                        $pattern .= '(?:';
                        $first    = true;
                        $req      = false;
                        $temp    .= '[';
                        break;
                    case ']':
                        --$s;
                        if ($s < 0) {
                            return null;
                        }
                        $pattern .= ')?';
                        $req      = true;
                        $temp    .= ']';
                        break;
                    case '{':
                        $var = true;
                        break;
                    case '}':
                        return null;
                    default:
                        $pattern .= \preg_quote($part, '%');
                        $temp    .= $part;
                }
            }
        }
        if (
            $var
            || $s
        ) {
            return null;
        }
        $pattern .= '$%D';

        return [
            $base,
            $pattern,
            $args,
            $temp,
            $argsReq,
        ];
    }
}
