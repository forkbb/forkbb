<?php

namespace ForkBB\Core;

use InvalidArgumentException;

class Router
{
    const OK = 200;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const NOT_IMPLEMENTED = 501;

    /**
     * Массив постоянных маршрутов
     * @var array
     */
    protected $statical = [];

    /**
     * Массив динамических маршрутов
     * @var array
     */
    protected $dynamic = [];

    /**
     * Список методов доступа
     * @var array
     */
    protected $methods = [];

    /**
     * Массив для построения ссылок
     * @var array
     */
    protected $links = [];

    /**
     * Базовый url сайта
     * @var string
     */
    protected $baseUrl;

    /**
     * Host сайта
     * @var string
     */
    protected $host;

    /**
     * Префикс uri
     * @var string
     */
    protected $prefix;

    /**
     * Длина префикса в байтах
     * @var int
     */
    protected $length;

    protected $subSearch = [
        '/',
        '\\',
    ];

    protected $subRepl = [
        '(_slash_)',
        '(_backslash_)',
    ];

    /**
     * Конструктор
     *
     * @param string $base
     */
    public function __construct($base)
    {
        $this->baseUrl = $base;
        $this->host    = \parse_url($base, PHP_URL_HOST);
        $this->prefix  = \parse_url($base, PHP_URL_PATH);
        $this->length  = \strlen($this->prefix);
    }

    /**
     * Проверка url на принадлежность форуму
     *
     * @param mixed $url
     * @param string $defMarker
     * @param array $defArgs
     *
     * @return string
     */
    public function validate($url, string $defMarker, array $defArgs = []): string
    {
        if (\is_string($url)
            && \parse_url($url, PHP_URL_HOST) === $this->host
            && ($route = $this->route('GET', \rawurldecode(\parse_url($url, \PHP_URL_PATH))))
            && $route[0] === self::OK
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
     *
     * @param string $marker
     * @param array $args
     *
     * @return string
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

        list($link, $names, $request) = $data;
        $data = [];
        // перечисление имен переменных для построения ссылки
        foreach ($names as $name) {
            // значение есть
            if (isset($args[$name])) {
                // кроме page = 1
                if ($name !== 'page' || $args[$name] !== 1) {
                    $data['{' . $name . '}'] = \rawurlencode(\str_replace($this->subSearch, $this->subRepl, $args[$name]));
                    continue;
                }
            }

            // значения нет, но оно обязательно
            if ($request[$name]) {
                return $result . '/';
            // значение не обязательно
            } else {
//                $link = preg_replace('%\[[^\[\]{}]*{' . preg_quote($name, '%') . '}[^\[\]{}]*\]%', '', $link);
                $link = \preg_replace('%\[[^\[\]]*?{' . \preg_quote($name, '%') . '}[^\[\]]*+(\[((?>[^\[\]]*+)|(?1))+\])*?\]%', '', $link);
            }
        }
        $link = \str_replace(['[', ']'], '', $link);

        return $result . \strtr($link, $data) . $anchor;
    }

    /**
     * Метод определяет маршрут
     *
     * @param string $method
     * @param string $uri
     *
     * @return array
     */
    public function route(string $method, string $uri): array
    {
        $head = $method == 'HEAD';

        if (empty($this->methods[$method]) && (! $head || empty($this->methods['GET']))) {
            return [self::NOT_IMPLEMENTED];
        }

        if ($this->length) {
            if (0 === \strpos($uri, $this->prefix)) {
                $uri = \substr($uri, $this->length);
            } else {
                return [self::NOT_FOUND];
            }
        }

        $allowed = [];

        if (isset($this->statical[$uri])) {
            if (isset($this->statical[$uri][$method])) {
                list($handler, $marker) = $this->statical[$uri][$method];
                return [self::OK, $handler, [], $marker];
            } elseif ($head && isset($this->statical[$uri]['GET'])) {
                list($handler, $marker) = $this->statical[$uri]['GET'];
                return [self::OK, $handler, [], $marker];
            } else {
                $allowed = \array_keys($this->statical[$uri]);
            }
        }

        $pos = \strpos($uri, '/', 1);
        $base = false === $pos ? $uri : \substr($uri, 0, $pos);

        if (isset($this->dynamic[$base])) {
            foreach ($this->dynamic[$base] as $pattern => $data) {
                if (! \preg_match($pattern, $uri, $matches)) {
                    continue;
                }

                if (isset($data[$method])) {
                    list($handler, $keys, $marker) = $data[$method];
                } elseif ($head && isset($data['GET'])) {
                    list($handler, $keys, $marker) = $data['GET'];
                } else {
                    $allowed += \array_keys($data);
                    continue;
                }

                $args = [];
                foreach ($keys as $key) {
                    if (isset($matches[$key])) { // ???? может isset($matches[$key][0]) тут поставить?
                        $args[$key] = isset($matches[$key][0]) ? \str_replace($this->subRepl, $this->subSearch, $matches[$key]) : null;
                    }
                }
                return [self::OK, $handler, $args, $marker];
            }
        }
        if (empty($allowed)) {
            return [self::NOT_FOUND];
        } else {
            return [self::METHOD_NOT_ALLOWED, $allowed];
        }
    }

    /**
     * Метод добавляет маршрут
     *
     * @param string|array $method
     * @param string $route
     * @param string $handler
     * @param string $marker
     */
    public function add($method, string $route, string $handler, string $marker = null): void
    {
        if (\is_array($method)) {
            foreach ($method as $m) {
                $this->methods[$m] = 1;
            }
        } else {
            $this->methods[$method] = 1;
        }

        $link   = $route;
        $anchor = '';
        if (false !== \strpos($route, '#')) {
            list($route, $anchor) = \explode('#', $route, 2);
            $anchor = '#' . $anchor;
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
            if (false === $data) {
                throw new InvalidArgumentException('Route is incorrect');
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
                $this->links[$marker] = [$data[3] . $anchor, $data[2], $data[4]];
            } else {
                $this->links[$marker] = $link;
            }
        }
    }

    /**
     * Метод разбирает динамический маршрут
     *
     * @param string $route
     *
     * @return array|null
     */
    protected function parse(string $route): ?array
    {
        $parts = \preg_split('%([\[\]{}/])%', $route, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);

        $s = 1;
        $base = $parts[0];
        if ($parts[0] === '/') {
            $s = 2;
            $base .= $parts[1];
        }
        if (isset($parts[$s]) && $parts[$s] !== '/' && $parts[$s] !== '[') {
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
                        $data = \explode(':', $buffer, 2);
                        if (! isset($data[1])) {
                            $data[1] = '[^/\x00-\x1f]+';
                        }
                        if ($data[0] === '' || $data[1] === '' || \is_numeric($data[0][0])) {
                            return null;
                        }
                        $pattern .= '(?P<' . $data[0] . '>' . $data[1] . ')';
                        $args[]   = $data[0];
                        $temp    .= '{' . $data[0] . '}';
                        $var      = false;
                        $buffer   = '';
                        $argsReq[$data[0]] = $req;
                        break;
                    default:
                        $buffer .= $part;
                }
            } elseif ($first) {
                switch ($part) {
                    case '/':
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
        if ($var || $s) {
            return null;
        }
        $pattern .= '$%D';
        return [$base, $pattern, $args, $temp, $argsReq];
    }
}
