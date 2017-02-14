<?php

namespace ForkBB\Models\Actions;

use ForkBB\Core\Cache;
use R2\DependencyInjection\ContainerInterface;
use InvalidArgumentException;

class CacheLoader
{
    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    /**
     * @var ForkBB\Core\Cache
     */
    protected $cache;

    /**
     * Конструктор
     * @param Cache $cache
     * @param ContainerInterface $container
     */
    public function __construct(Cache $cache, ContainerInterface $container)
    {
        $this->cache = $cache;
        $this->c = $container;
    }

    /**
     * Загрузка данных из кэша (генерация кэша при отсутствии или по требованию)
     * @param string $key
     * @param bool $update
     * @return mixed
     * @throws \InvalidArgumentException
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

    /**
     * Загрузка данных по разделам из кэша (генерация кэша при условии)
     * @return array
     */
    public function loadForums()
    {
        $mark = $this->cache->get('forums_mark');
        $key = 'forums_' . $this->c->get('user')['g_id'];

        if (empty($mark)) {
            $this->cache->set('forums_mark', time());
            $value = $this->c->get('get forums');
            $this->cache->set($key, [time(), $value]);
            return $value;
        }

        $result = $this->cache->get($key);

        if (empty($result) || $result[0] < $mark) {
            $value = $this->c->get('get forums');
            $this->cache->set($key, [time(), $value]);
            return $value;
        }

        return $result[1];
    }
}
