<?php

namespace ForkBB\Core;

class Request
{
    /**
     * @var Secury
     */
    protected $secury;

    /**
     * Конструктор
     *
     * @param Secury $secury
     */
    public function __construct($secury)
    {
        $this->secury = $secury;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isRequest($key)
    {
        return $this->isPost($key) || $this->isGet($key);
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function request($key, $default = null)
    {
        if (($result = $this->post($key)) === null
            && ($result = $this->get($key)) === null
        ) {
            return $default;
        }
        return $result;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function requestStr($key, $default = null)
    {
        if (($result = $this->postStr($key)) === null
            && ($result = $this->getStr($key)) === null
        ) {
            return $default;
        }
        return $result;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function requestInt($key, $default = null)
    {
        if (($result = $this->postInt($key)) === null
            && ($result = $this->getInt($key)) === null
        ) {
            return $default;
        }
        return $result;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function requestBool($key, $default = null)
    {
        if (($result = $this->postBool($key)) === null
            && ($result = $this->getBool($key)) === null
        ) {
            return $default;
        }
        return $result;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isPost($key)
    {
        return isset($_POST[$key]);
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function post($key, $default = null)
    {
        if (isset($_POST[$key])) {
            return $this->secury->replInvalidChars($_POST[$key]);
        }
        return $default;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function postStr($key, $default = null)
    {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            return (string) $this->secury->replInvalidChars($_POST[$key]);
        }
        return $default;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function postInt($key, $default = null)
    {
        if (isset($_POST[$key]) && is_numeric($_POST[$key]) && is_int(0 + $_POST[$key])) {
            return (int) $_POST[$key];
        }
        return $default;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function postBool($key, $default = null)
    {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            return (bool) $_POST[$key];
        }
        return $default;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function postKey($key, $default = null)
    {
        if (isset($_POST[$key]) && is_array($_POST[$key])) {
            $k = key($_POST[$key]);
            if (null !== $k) {
                return is_int($k) ? (int) $k : (string) $this->secury->replInvalidChars($k);
            }
        }
        return $default;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isGet($key)
    {
        return isset($_GET[$key]);
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (isset($_GET[$key])) {
            return $this->secury->replInvalidChars($_GET[$key]);
        }
        return $default;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getStr($key, $default = null)
    {
        if (isset($_GET[$key]) && is_string($_GET[$key])) {
            return (string) $this->secury->replInvalidChars($_GET[$key]);
        }
        return $default;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getInt($key, $default = null)
    {
        if (isset($_GET[$key]) && is_numeric($_GET[$key]) && is_int(0 + $_GET[$key])) {
            return (int) $_GET[$key];
        }
        return $default;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getBool($key, $default = null)
    {
        if (isset($_GET[$key]) && is_string($_GET[$key])) {
            return (bool) $_GET[$key];
        }
        return $default;
    }
}
