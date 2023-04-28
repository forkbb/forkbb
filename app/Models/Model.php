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

class Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'unknown';

    /**
     * Данные модели
     */
    protected array $zAttrs = [];

    /**
     * Вычисленные данные модели
     */
    protected array $zAttrsCalc = [];

    /**
     * Зависимости свойств
     */
    protected array $zDepend = [];

    public function __construct(protected Container $c)
    {
    }

    /**
     * Проверяет наличие свойства
     */
    public function __isset(string $name): bool
    {
        return \array_key_exists($name, $this->zAttrs)
            || \array_key_exists($name, $this->zAttrsCalc)
            || \method_exists($this, 'get' . $name);
    }

    /**
     * Удаляет свойство
     */
    public function __unset(string $name): void
    {
        unset($this->zAttrs[$name]);
        $this->unsetCalc($name);
    }

    /**
     * Удаляет вычисленные зависимые свойства
     */
    protected function unsetCalc(string $name): void
    {
        unset($this->zAttrsCalc[$name]);
        unset($this->zAttrsCalc['censor' . \ucfirst($name)]);

        if (isset($this->zDepend[$name])) {
            $this->zAttrsCalc = \array_diff_key($this->zAttrsCalc, \array_flip($this->zDepend[$name]));
        }
    }

    /**
     * Устанавливает значение для свойства
     */
    public function __set(string $name, mixed $value): void
    {
        $this->unsetCalc($name);

        if (\method_exists($this, $method = 'set' . $name)) {
            $this->$method($value);
        } else {
            $this->zAttrs[$name] = $value;
        }
    }

    /**
     * Устанавливает значение для свойства
     * Без вычислений, но со сбросом зависимых свойст и вычисленного значения
     */
    public function setAttr(string $name, mixed $value): Model
    {
        $this->unsetCalc($name);
        $this->zAttrs[$name] = $value;

        return $this;
    }

    /**
     * Устанавливает значения для свойств
     * Сбрасывает вычисленные свойства
     */
    public function setAttrs(array $attrs): Model
    {
        $this->zAttrs     = $attrs; //????
        $this->zAttrsCalc = [];

        return $this;
    }

    /**
     * Возвращает значение свойства
     * Или возвращает внешний метод Models\Method
     */
    public function __get(string $name): mixed
    {
        if (\array_key_exists($name, $this->zAttrsCalc)) {
            return $this->zAttrsCalc[$name];
        } elseif (\method_exists($this, $method = 'get' . $name)) {
            return $this->zAttrsCalc[$name] = $this->$method();
        } elseif (\array_key_exists($name, $this->zAttrs)) {
            return $this->zAttrs[$name];
        } elseif (
            \str_starts_with($name, 'censor')
            && isset($this->zAttrs[$root = \lcfirst(\substr($name, 6))])
        ) {
            return $this->zAttrsCalc[$name] = $this->c->censorship->censor($this->zAttrs[$root]);
        }

        $x = \ord($name);

        if ($x > 90 || $x < 65) {
            return null;
        } else {
            $key = $this->cKey . '/' . \lcfirst($name);

            return $this->c->$key->setModel($this);
        }
    }

    /**
     * Возвращает значение свойства
     * Без вычислений
     */
    public function getAttr(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->zAttrs) ? $this->zAttrs[$name] : $default;
    }

    /**
     * Выполняет подгружаемый метод при его наличии
     */
    public function __call(string $name, array $args): mixed
    {
        $key = $this->cKey . '/' . $name;

        return $this->c->$key->setModel($this)->$name(...$args);
    }
}
