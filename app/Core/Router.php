<?php

namespace ForkBB\Core;

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
     * Массив для построения реальных ссылок
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

    /**
     * Конструктор
     * @param string $prefix
     */
    public function __construct($base = '')
    {
        $this->baseUrl = $base;
        $this->host = parse_url($base, PHP_URL_HOST);
        $this->prefix = parse_url($base, PHP_URL_PATH);
        $this->length = strlen($this->prefix);
    }

    /**
     * Проверка url на принадлежность форуму
     * @param string $url
     * @param string $defMarker
     * @param array $defArgs
     * @return string
     */
    public function validate($url, $defMarker, array $defArgs = [])
    {
        if (parse_url($url, PHP_URL_HOST) === $this->host
            && ($route = $this->route('GET', rawurldecode(parse_url($url, PHP_URL_PATH))))
            && $route[0] === self::OK
        ) {
            return $url;
        } else {
            return $this->link($defMarker, $defArgs);
        }
    }

    /**
     * Возвращает реальный url
     * @param string $marker
     * @param array $args
     * @return string
     */
    public function link($marker, array $args = [])
    {
        $result = $this->baseUrl; //???? http и https
        if (isset($this->links[$marker])) {
            $s = $this->links[$marker];
            foreach ($args as $key => $val) {
                if ($key == '#') {
                    $s .= '#' . rawurlencode($val); //????
                    continue;
                }
                $s = preg_replace(
                    '%\{' . preg_quote($key, '%') . '(?::[^{}]+)?\}%',
                    rawurlencode($val),
                    $s
                );
            }
            $s = preg_replace('%\[[^{}\[\]]*\{[^}]+\}[^{}\[\]]*\]%', '', $s);
            if (strpos($s, '{') === false) {
                $result .= str_replace(['[', ']'], '', $s);
            } else {
                $result .= '/';
            }
        } else {
            $result .= '/';
        }
        return $result;
    }

    /**
     * Метод определяет маршрут
     * @param string $method
     * @param string $uri
     * @return array
     */
    public function route($method, $uri)
    {
        $head = $method == 'HEAD';

        if (empty($this->methods[$method]) && (! $head || empty($this->methods['GET']))) {
            return [self::NOT_IMPLEMENTED];
        }

        if ($this->length) {
            if (0 === strpos($uri, $this->prefix)) {
                $uri = substr($uri, $this->length);
            } else {
                return [self::NOT_FOUND];
            }
        }

        $allowed = [];

        if (isset($this->statical[$uri])) {
            if (isset($this->statical[$uri][$method])) {
                return [self::OK, $this->statical[$uri][$method], []];
            } elseif ($head && isset($this->statical[$uri]['GET'])) {
                return [self::OK, $this->statical[$uri]['GET'], []];
            } else {
                $allowed = array_keys($this->statical[$uri]);
            }
        }

        $pos = strpos(substr($uri, 1), '/');
        $base = false === $pos ? $uri : substr($uri, 0, ++$pos);

        if (isset($this->dynamic[$base])) {
            foreach ($this->dynamic[$base] as $pattern => $data) {
                if (! preg_match($pattern, $uri, $matches)) {
                    continue;
                }

                if (isset($data[$method])) {
                    $data = $data[$method];
                } elseif ($head && isset($data['GET'])) {
                    $data = $data['GET'];
                } else {
                    $allowed += array_keys($data);
                    continue;
                }

                $args = [];
                foreach ($data[1] as $key) {
                    if (isset($matches[$key])) {
                        $args[$key] = $matches[$key];
                    }
                }
                return [self::OK, $data[0], $args];
            }
        }


        if (empty($allowed)) {
            return [self::NOT_FOUND];
        } else {
            return [self::METHOD_NOT_ALLOWED, $allowed];
        }
    }

    /**
     * Метод добавдяет маршрут
     * @param string|array $method
     * @param string $route
     * @param string $handler
     * @param string $marker
     */
    public function add($method, $route, $handler, $marker = null)
    {
        if (is_array($method)) {
            foreach ($method as $m) {
                $this->methods[$m] = 1;
            }
        } else {
            $this->methods[$method] = 1;
        }

        $link = $route;
        if (($pos = strpos($route, '#')) !== false) {
            $route = substr($route, 0, $pos);
        }

        if (false === strpbrk($route, '{}[]')) {
            if (is_array($method)) {
                foreach ($method as $m) {
                    $this->statical[$route][$m] = $handler;
                }
            } else {
                $this->statical[$route][$method] = $handler;
            }
        } else {
            $data = $this->parse($route);
            if (false === $data) {
                throw new \Exception('Route is incorrect');
            }
            if (is_array($method)) {
                foreach ($method as $m) {
                    $this->dynamic[$data[0]][$data[1]][$m] = [$handler, $data[2]];
                }
            } else {
                $this->dynamic[$data[0]][$data[1]][$method] = [$handler, $data[2]];
            }
        }

        if ($marker) {
            $this->links[$marker] = $link;
        }
    }

    /**
     * Метод разбирает динамический маршрут
     * @param string $route
     * @return array|false
     */
    protected function parse($route)
    {
        $parts = preg_split('%([\[\]{}/])%', $route, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

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
        $var = false;
        $first = false;
        $buffer = '';
        $args = [];
        $s = 0;

        foreach ($parts as $part) {
            if ($var) {
                switch ($part) {
                    case '{':
                        return false;
                    case '}':
                        $data = explode(':', $buffer, 2);
                        if (! isset($data[1])) {
                            $data[1] = '[^/\x00-\x1f]+';
                        }
                        if ($data[0] === '' || $data[1] === '' || is_numeric($data[0]{0})) {
                            return false;
                        }
                        $pattern .= '(?P<' . $data[0] . '>' . $data[1] . ')';
                        $args[] = $data[0];
                        $var = false;
                        $buffer = '';
                        break;
                    default:
                        $buffer .= $part;
                }
            } elseif ($first) {
                switch ($part) {
                    case '/':
                        $first = false;
                        $pattern .= preg_quote($part, '%');
                        break;
                    default:
                        return false;
                }
            } else {
                switch ($part) {
                    case '[':
                        ++$s;
                        $pattern .= '(?:';
                        $first = true;
                        break;
                    case ']':
                        --$s;
                        if ($s < 0) {
                            return false;
                        }
                        $pattern .= ')?';
                        break;
                    case '{':
                        $var = true;
                        break;
                    case '}':
                        return false;
                    default:
                        $pattern .= preg_quote($part, '%');
                }
            }
        }
        if ($var || $s) {
            return false;
        }
        $pattern .= '$%D';
        return [$base, $pattern, $args];
    }
}
