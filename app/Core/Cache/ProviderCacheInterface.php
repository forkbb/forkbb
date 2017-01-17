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
    public function get($key, $default = null);

    /**
     * Установка данных в кэш по ключу
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     *
     * @return bool
     */
    public function set($key, $value, $ttl = null);

    /**
     * Удаление данных по ключу
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete($key);

    /**
     * Очистка кэша
     *
     * @return bool
     */
    public function clear();

    /**
     * Проверка наличия ключа
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key);
}
