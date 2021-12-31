<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core\Cache;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\InvalidArgumentException;
use DateInterval;
use DateTime;
use DateTimeZone;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class FileCache implements CacheInterface
{
    /**
     * Директория кэша
     * @var string
     */
    protected $cacheDir;

    public function __construct(string $dir, /* string */ $resetMark)
    {
        $dir = \rtrim($dir, '\\/');

        if (empty($dir)) {
            throw new CacheException('Cache directory unset');
        } elseif (! \is_dir($dir)) {
            throw new CacheException("Not a directory: {$dir}");
        } elseif (! \is_writable($dir)) {
            throw new CacheException("No write access to directory: {$dir}");
        }

        $this->cacheDir = $dir;

        if (\is_string($resetMark)) {
            $this->resetIfRequired($resetMark);
        }
    }

    /**
     * Получает данные из кэша по ключу
     */
    public function get($key, $default = null)
    {
        $file = $this->path($key);

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
     * Устанавливает данные в кэш по ключу
     */
    public function set($key, $value, $ttl = null)
    {
        $file = $this->path($key);

        if ($ttl instanceof DateInterval) {
            $expire = (new DateTime('now', new DateTimeZone('UTC')))->add($value)->getTimestamp();
        } else {
            $expire  = null === $ttl || $ttl < 1 ? 0 : \time() + $ttl; //????
        }

        $value   = \var_export($value, true);
        $content = "<?php\n\n\$expire = {$expire};\n\n\$data = {$value};\n";

        if (false === \file_put_contents($file, $content, \LOCK_EX)) {
            return false;
        } else {
            $this->invalidate($file);

            return true;
        }
    }

    /**
     * Удаляет данные по ключу
     */
    public function delete($key)
    {
        $file = $this->path($key);

        if (
            \is_file($file)
            && ! \unlink($file)
        ) {
            return false;
        }

        $this->invalidate($file);

        return true;
    }

    /**
     * Очищает папку кэша от php файлов (рекурсивно)
     */
    public function clear()
    {
        $dir      = new RecursiveDirectoryIterator($this->cacheDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir);
        $files    = new RegexIterator($iterator, '%\.php$%i', RegexIterator::MATCH);
        $result   = true;

        foreach ($files as $file) {
            $result = \unlink($file->getPathname()) && $result;
        }

        return $result;
    }

    /**
     * Получает данные по списку ключей
     */
    public function getMultiple($keys, $default = null)
    {
        $this->validateIterable($keys);

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Устанавливает данные в кэш по списку ключ => значение
     */
    public function setMultiple($values, $ttl = null)
    {
        $this->validateIterable($keys);

        $result = true;
        foreach ($values as $key => $value) {
            $result = $this->set($key, $value, $ttl) && $result;
        }

        return $result;
    }

    /**
     * Удаляет данные по списку ключей
     */
    public function deleteMultiple($keys)
    {
        $this->validateIterable($keys);

        $result = true;
        foreach ($keys as $key) {
            $result = $this->delete($key) && $result;
        }

        return $result;
    }

    /**
     * Проверяет кеш на наличие ключа
     */
    public function has($key)
    {
        $file = $this->path($key);

        if (\is_file($file)) {
            require $file;

            if (
                isset($expire, $data)
                && (
                    $expire < 1
                    || $expire > \time()
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет ключ
     * Генерирует путь до файла
     */
    protected function path($key): string
    {
        if (! \is_string($key)) {
            throw new InvalidArgumentException('Expects a string, got: ' . \gettype($key));
        }
        if (! \preg_match('%^[a-z0-9_\.]+$%Di', $key)) {
            throw new InvalidArgumentException('Key is not a legal value');
        }
        if ('poll' == \substr($key, 0, 4)) {
            return $this->cacheDir . "/polls/{$key}.php";
        } else {
            return $this->cacheDir . "/cache_{$key}.php";
        }
    }

    /**
     * Очищает opcache и apc от закэшированного файла
     */
    protected function invalidate(string $file): void
    {
        if (\function_exists('\\opcache_invalidate')) {
            \opcache_invalidate($file, true);
        } elseif (\function_exists('\\apc_delete_file')) {
            \apc_delete_file($file);
        }
    }

    /**
     * Проверяет, является ли переменная итерируемой
     */
    protected function validateIterable($iterable): void
    {
        if (! \is_iterable($iterable)) {
            throw new InvalidArgumentException('Expects a iterable, got: ' . \gettype($iterable));
        }
    }

    /**
     * Сбрасывает кеш при изменении $resetMark
     */
    protected function resetIfRequired(string $resetMark): void
    {
        if (empty($resetMark)) {
            return;
        }

        $hash = \sha1($resetMark);

        if ($this->get('reset_mark_hash') !== $hash) {
            $this->clear();
            $this->set('reset_mark_hash', $hash);
        }
    }
}
