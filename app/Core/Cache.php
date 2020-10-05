<?php

namespace ForkBB\Core;

use Psr\SimpleCache\CacheInterface;

class Cache
{
    /**
     * Провайдер доступа к кэшу
     * @var ProviderInterfaces
     */
    protected $provider;

    public function __construct(CacheInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Получение данных из кэша по ключу
     */
    public function get(string $key, $default = null) /* : mixed */
    {
        return $this->provider->get($key, $default);
    }

    /**
     * Установка данных в кэш по ключу
     */
    public function set(string $key, /* mixed */ $value, int $ttl = null): bool
    {
        return $this->provider->set($key, $value, $ttl);
    }

    /**
     * Удаление данных по ключу
     */
    public function delete(string $key): bool
    {
        return $this->provider->delete($key);
    }

    /**
     * Очистка кэша
     */
    public function clear(): bool
    {
        return $this->provider->clear();
    }

    /**
     * Проверка наличия ключа
     */
    public function has(string $key): bool
    {
        return $this->provider->has($key);
    }
}
