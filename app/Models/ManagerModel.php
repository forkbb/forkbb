<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models;

use ForkBB\Models\Model;

class ManagerModel extends Model
{
    /**
     * @var array
     */
    protected $repository = [];

    public function get($key)
    {
        return $this->repository[$key] ?? null;
    }

    public function set($key, /* mixed */ $value): ManagerModel
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
        $key = \str_replace(['ForkBB\\Models\\', 'ForkBB\\', '\\'], '', \get_class($this));

        return $this->c->{$key . \ucfirst($name)}->setManager($this);
    }

    /**
     * Выполняет подгружаемый метод при его наличии
     */
    public function __call(string $name, array $args) /* : mixed */
    {
        return $this->__get($name)->$name(...$args);
    }
}
