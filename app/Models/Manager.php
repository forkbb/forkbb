<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
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
     * Ключ модели для контейнера
     */
    protected string $cKey = 'unknown';

    protected array $repository = [];

    public function __construct(protected Container $c)
    {
    }

    public function get(int|string $key)
    {
        return $this->repository[$key] ?? null;
    }

    public function set(int|string $key, mixed $value): self
    {
        $this->repository[$key] = $value;

        return $this;
    }

    public function isset(int|string $key): bool
    {
        return \array_key_exists($key, $this->repository);
    }

    /**
     * Возвращает action по его имени
     */
    public function __get(string $name): mixed
    {
        $x = \ord($name);

        if ($x > 90 || $x < 65) {
            return 'repository' === $name ? $this->repository : null;

        } else {
            $key = $this->cKey . '/' . \lcfirst($name);

            return $this->c->$key->setManager($this);
        }
    }

    /**
     * Выполняет подгружаемый метод при его наличии
     */
    public function __call(string $name, array $args): mixed
    {
        $key = $this->cKey . '/' . $name;

        return $this->c->$key->setManager($this)->$name(...$args);
    }
}
