<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models;

use ForkBB\Core\Container;

class Manager
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Ключ модели для контейнера
     * @var string
     */
    protected $cKey = 'unknown';

    /**
     * @var array
     */
    protected $repository = [];

    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    public function get($key)
    {
        return $this->repository[$key] ?? null;
    }

    public function set($key, /* mixed */ $value): self
    {
        $this->repository[$key] = $value;

        return $this;
    }

    public function isset($key): bool
    {
        return \array_key_exists($key, $this->repository);
    }

    /**
     * Возвращает action по его имени
     */
    public function __get(string $name) /* : mixed */
    {
        $key = $this->cKey . '/' . $name;

        return $this->c->$key->setManager($this);
    }

    /**
     * Выполняет подгружаемый метод при его наличии
     */
    public function __call(string $name, array $args) /* : mixed */
    {
        return $this->__get($name)->$name(...$args);
    }
}
