<?php

namespace ForkBB\Core\Cache;

use RuntimeException;
use InvalidArgumentException;

class FileCache implements ProviderCacheInterface
{
    /**
     * Директория кэша
     * @var string
     */
    protected $cacheDir;

    /**
     * Конструктор
     *
     * @param string $dir
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function __construct($dir)
    {
        if (empty($dir) || ! \is_string($dir)) {
            throw new InvalidArgumentException('Cache directory must be set to a string');
        } elseif (! \is_dir($dir)) {
            throw new RuntimeException("`$dir`: Not a directory");
        } elseif (! \is_writable($dir)) {
            throw new RuntimeException("No write access to `$dir` directory");
        }
        $this->cacheDir = $dir;
    }

    /**
     * Получение данных из кэша по ключу
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $file = $this->file($key);
        if (\file_exists($file)) {
            require $file;

            if (isset($expire) && isset($data)
                && ($expire < 1 || $expire > \time())
            ) {
                return $data;
            }
        }
        return $default;
    }

    /**
     * Установка данных в кэш по ключу
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     *
     * @throws RuntimeException
     *
     * @return bool
     */
    public function set($key, $value, $ttl = null)
    {
        $file = $this->file($key);
        $expire = null === $ttl || $ttl < 1 ? 0 : \time() + $ttl;
        $content = "<?php\n\n" . '$expire = ' . $expire . ";\n\n" . '$data = ' . \var_export($value, true) . ";\n";
        if (false === \file_put_contents($file, $content, \LOCK_EX)) {
            throw new RuntimeException("The key '$key' can not be saved");
        } else {
            $this->invalidate($file);
            return true;
        }
    }

    /**
     * Удаление данных по ключу
     *
     * @param string $key
     *
     * @throws RuntimeException
     *
     * @return bool
     */
    public function delete($key)
    {
        $file = $this->file($key);
        if (\file_exists($file)) {
            if (\unlink($file)) {
                $this->invalidate($file);
                return true;
            } else {
                throw new RuntimeException("The key `$key` could not be removed");
            }
        } else {
            return true;
        }
    }

    /**
     * Очистка кэша
     *
     * @return bool
     */
    public function clear()
    {
        $d = \dir($this->cacheDir);
        if (! $d) {
            return false;
        }
        $result = true;
        while (($entry = $d->read()) !== false) {
            if (\substr($entry, -4) == '.php') {
                $f = \unlink($this->cacheDir . '/' . $entry);
                $result = $result && $f;
            }
        }
        $d->close();
        return $result;
    }

    /**
     * Проверка наличия ключа
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return null !== $this->get($key);
    }

    /**
     * Генерация имени файла по ключу
     *
     * @param string $key
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    protected function file($key)
    {
        if (\is_string($key) && \preg_match('%^[a-z0-9_-]+$%Di', $key)) {
            return $this->cacheDir . '/cache_' . $key . '.php';
        }
        throw new InvalidArgumentException("Key '$key' contains invalid characters.");
    }

    /**
     * Очистка opcache и apc от закэшированного файла
     *
     * @param string $file
     */
    protected function invalidate($file)
    {
        if (\function_exists('opcache_invalidate')) {
            \opcache_invalidate($file, true);
        } elseif (\function_exists('apc_delete_file')) {
            \apc_delete_file($file);
        }
    }
}
