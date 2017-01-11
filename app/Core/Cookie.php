<?php

namespace ForkBB\Core;

class Cookie
{
    /**
     * @var Secury
     */
    protected $secury;

    /**
     * Префикс для наименований
     * @var string
     */
    protected $prefix;

    /**
     * Домен
     * @var string
     */
    protected $domain;

    /**
     * Путь
     * @var string
     */
    protected $path;

    /**
     * Флаг передачи кук по защищенному соединению
     * @var bool
     */
    protected $secure;

    /**
     * Конструктор
     *
     * @param Secury $secury
     * @param array $options
     */
    public function __construct($secury, array $options)
    {
        $this->secury = $secury;

        $options += [
            'prefix' => '',
            'domain' => '',
            'path'   => '',
            'secure' => false,
        ];

        $this->prefix = (string) $options['prefix'];
        $this->domain = (string) $options['domain'];
        $this->path = (string) $options['path'];
        $this->secure = (bool) $options['secure'];
    }

    /**
     * Устанавливает куку
     *
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     *
     * @return bool
     */
    public function set($name, $value, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = true)
    {
        $name = $this->prefix . $name;
        $path = isset($path) ? $path : $this->path;
        $domain = isset($domain) ? $domain : $this->domain;
        $secure = $this->secure || (bool) $secure;
        $result = setcookie($name, $value, $expire, $path, $domain, $secure, (bool) $httponly);
        if ($result) {
            $_COOKIE[$name] = $value;
        }
        return $result;
    }

    /**
     * Получает значение куки
     *
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        $name = $this->prefix . $name;
        return isset($_COOKIE[$name]) ? $this->secury->replInvalidChars($_COOKIE[$name]) : $default;
    }

    /**
     * Удаляет куку
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     *
     * @return bool
     */
    public function delete($name, $path = null, $domain = null)
    {
        $result = $this->set($name, '', 1, $path, $domain);
        if ($result) {
            unset($_COOKIE[$this->prefix . $name]);
        }
        return $result;
    }
}
