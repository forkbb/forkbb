<?php

namespace ForkBB\Core\Cache;

interface ProviderCacheInterface
{
    /**
     * Получение данных из кэша по ключу
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Установка данных в кэш по ключу
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     *
     * @return bool
     */
    public function set(string $key, $value, int $ttl = null): bool;

    /**
     * Удаление данных по ключу
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Очистка кэша
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * Проверка наличия ключа
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool;
}
