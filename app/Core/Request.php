<?php

namespace ForkBB\Core;

class Request
{
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
     * @param mixed $default
     *
     * @return mixed
     */
    public function post($key, $default = null)
    {
        if (isset($_POST[$key])) {
            return $this->replBadChars($_POST[$key]);
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
            return $this->replBadChars($_POST[$key]);
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
    public function get($key, $default = null)
    {
        if (isset($_GET[$key])) {
            return $this->replBadChars($_GET[$key]);
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
            return $this->replBadChars($_GET[$key]);
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

    /**
     * @param string|array $data
     *
     * @return string|array
     */
    protected function replBadChars($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'replBadChars'], $data);
        }

        // slow, small memory
        //$data = mb_convert_encoding((string) $data, 'UTF-8', 'UTF-8');
        // fast, large memory
        $data = htmlspecialchars_decode(htmlspecialchars((string) $data, ENT_SUBSTITUTE, 'UTF-8'));

        // Remove control characters
        return preg_replace('%[\x00-\x08\x0B-\x0C\x0E-\x1F]%', '', $data);
    }
}
