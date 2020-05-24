<?php

namespace ForkBB\Core;

use ForkBB\Core\Cache\ProviderCacheInterface;

class Cache
{
    /**
     * Провайдер доступа к кэшу
     * @var ProviderInterfaces
     */
    protected $provider;

    /**
     * Конструктор
     *
     * @param ProviderInterfaces $provider
     */
    public function __construct(ProviderCacheInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Получение данных из кэша по ключу
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->provider->get($key, $default);
    }

    /**
     * Установка данных в кэш по ключу
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     *
     * @return bool
     */
    public function set(string $key, $value, int $ttl = null): bool
    {
        return $this->provider->set($key, $value, $ttl);
    }

    /**
     * Удаление данных по ключу
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete(striing $key): bool
    {
        return $this->provider->delete($key);
    }

    /**
     * Очистка кэша
     *
     * @return bool
     */
    public function clear(): bool
    {
        return $this->provider->clear();
    }

    /**
     * Проверка наличия ключа
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->provider->has($key);
    }
}
