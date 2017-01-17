<?php

namespace ForkBB\Models\Actions;

use ForkBB\Core\Cache;
use R2\DependencyInjection\ContainerInterface;
use R2\DependencyInjection\ContainerAwareInterface;
use InvalidArgumentException;

class CacheLoader implements ContainerAwareInterface
{
    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    /**
     * Инициализация контейнера
     *
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->c = $container;
    }

    /**
     * @var ForkBB\Core\Cache
     */
    protected $cache;

    /**
     * Конструктор
     *
     * @param ForkBB\Core\Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Загрузка данных из кэша (генерация кэша при отсутствии или по требованию)
     *
     * @param string $key
     * @param bool $update
     *
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public function load($key, $update = false)
    {
        if (preg_match('%\p{Lu}%u', $key)) {
            throw new InvalidArgumentException('The key must not contain uppercase letters');
        }
        if (! $update && $this->cache->has($key)) {
            return $this->cache->get($key);
        } else {
            $value = $this->c->get('get ' . $key);
            $this->cache->set($key, $value);
            if ($update) {
                $this->c->set($key, $value);
            }
            return $value;
        }
    }
}
