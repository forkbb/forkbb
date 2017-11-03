<?php

namespace ForkBB\Core;

use RuntimeException;
use UnexpectedValueException;
use InvalidArgumentException;

class Secury
{
    /**
     * Algorithm and salt for hash_hmac
     * @var array
     */
    protected $hmac;

    /**
     * Конструктор
     *
     * @param array $hmac
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function __construct(array $hmac)
    {
        if (empty($hmac['salt']) || empty($hmac['algo'])) {
            throw new InvalidArgumentException('Algorithm and salt can not be empty');
        }
        if (! in_array($hmac['algo'], hash_algos())) {
            throw new UnexpectedValueException('Algorithm not supported');
        }
        $this->hmac = $hmac;
    }

    /**
     * Обертка для hash_hmac
     *
     * @param string $data
     *
     * @return string
     */
    public function hash($data)
    {
        return $this->hmac($data, md5(__DIR__));
    }

    /**
     * Обертка для hash_hmac
     *
     * @param string $data
     * @param string $key
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    public function hmac($data, $key)
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Key can not be empty');
        }
        return hash_hmac($this->hmac['algo'], $data, $this->hmac['salt'] . $key);
    }

    /**
     * Возвращает случайный набор байтов заданной длины
     *
     * @param int $len
     *
     * @throws RuntimeException
     *
     * @return string
     */
    public function randomKey($len)
    {
        $key = '';
        if (function_exists('random_bytes')) {
            $key .= (string) random_bytes($len);
        }
        if (strlen($key) < $len && function_exists('mcrypt_create_iv')) {
            $key .= (string) mcrypt_create_iv($len, MCRYPT_DEV_URANDOM);
        }
        if (strlen($key) < $len && function_exists('openssl_random_pseudo_bytes')) {
            $tmp = (string) openssl_random_pseudo_bytes($len, $strong);
            if ($strong) {
                $key .= $tmp;
            }
        }
        if (strlen($key) < $len) {
            throw new RuntimeException('Could not gather sufficient random data');
        }
    	return $key;
    }

    /**
     * Возвращает случайную строку заданной длины состоящую из символов 0-9 и a-f
     *
     * @param int $len
     *
     * @return string
     */
    public function randomHash($len)
    {
        return substr(bin2hex($this->randomKey($len)), 0, $len);
    }

    /**
     * Возвращает случайную строку заданной длины состоящую из цифр, латиницы,
     * знака минус и символа подчеркивания
     *
     * @param int $len
     *
     * @return string
     */
    public function randomPass($len)
    {
        $key = $this->randomKey($len);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $result = '';
        for ($i = 0; $i < $len; ++$i) {
            $result .= substr($chars, (ord($key[$i]) % strlen($chars)), 1);
        }
        return $result;
    }

    /**
     * Replacing invalid UTF-8 characters and remove control characters
     *
     * @param string|array $data
     *
     * @return string|array
     */
    public function replInvalidChars($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'replInvalidChars'], $data);
        }
        // Replacing invalid UTF-8 characters
        // slow, small memory
        //$data = mb_convert_encoding((string) $data, 'UTF-8', 'UTF-8');
        // fast, large memory
        $data = htmlspecialchars_decode(htmlspecialchars((string) $data, ENT_SUBSTITUTE, 'UTF-8'));
        // Remove control characters
        return preg_replace('%[\x00-\x08\x0B-\x0C\x0E-\x1F]%', '', $data);
    }
}
