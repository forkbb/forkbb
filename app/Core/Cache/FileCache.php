<?php

namespace ForkBB\Core\Cache;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RuntimeException;
use InvalidArgumentException;

class FileCache implements ProviderCacheInterface
{
    /**
     * Директория кэша
     * @var string
     */
    protected $cacheDir;

    public function __construct(string $dir)
    {
        if (
            empty($dir)
            || ! \is_string($dir)
        ) {
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
     */
    public function get(string $key, /* mixed */ $default = null) /* : mixed */
    {
        $file = $this->file($key);
        if (\is_file($file)) {
            require $file;

            if (
                isset($expire, $data)
                && (
                    $expire < 1
                    || $expire > \time()
                )
            ) {
                return $data;
            }
        }

        return $default;
    }

    /**
     * Установка данных в кэш по ключу
     */
    public function set(string $key, /* mixed */ $value, int $ttl = null): bool
    {
        $file    = $this->file($key);
        $expire  = null === $ttl || $ttl < 1 ? 0 : \time() + $ttl;
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
     */
    public function delete(string $key): bool
    {
        $file = $this->file($key);
        if (\is_file($file)) {
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
     */
    public function clear(): bool
    {
        $dir      = new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir);
        $files    = new RegexIterator($iterator, '%\.php$%i', RegexIterator::MATCH);

        $result = true;
        foreach ($files as $file) {
            $result = \unlink($file->getPathname()) && $result;
        }

        return $result;
    }

    /**
     * Проверка наличия ключа
     */
    public function has(string $key): bool
    {
        return null !== $this->get($key);
    }

    /**
     * Генерация имени файла по ключу
     */
    protected function file(string $key): string
    {
        if (
            \is_string($key)
            && \preg_match('%^[a-z0-9_-]+$%Di', $key)
        ) {
            return $this->cacheDir . '/cache_' . $key . '.php';
        }
        throw new InvalidArgumentException("Key '$key' contains invalid characters.");
    }

    /**
     * Очистка opcache и apc от закэшированного файла
     */
    protected function invalidate(string $file): void
    {
        if (\function_exists('\\opcache_invalidate')) {
            \opcache_invalidate($file, true);
        } elseif (\function_exists('\\apc_delete_file')) {
            \apc_delete_file($file);
        }
    }
}
