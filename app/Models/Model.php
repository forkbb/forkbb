<?php

namespace ForkBB\Models;

use ForkBB\Core\Container;

class Model
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Данные модели
     * @var array
     */
    protected $zAttrs = [];

    /**
     * Вычисленные данные модели
     * @var array
     */
    protected $zAttrsCalc = [];

    /**
     * Зависимости свойств
     * @var array
     */
    protected $zDepend = [];

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
    }

    /**
     * Проверяет наличие свойства
     *
     * @param mixed $name
     *
     * @return bool
     */
    public function __isset($name): bool
    {
        return \array_key_exists($name, $this->zAttrs)
            || \array_key_exists($name, $this->zAttrsCalc)
            || \method_exists($this, 'get' . $name);
    }

    /**
     * Удаляет свойство
     *
     * @param mixed $name
     */
    public function __unset($name): void
    {
        unset($this->zAttrs[$name]);
        $this->unsetCalc($name);
    }

    /**
     * Удаляет вычисленные зависимые свойства
     *
     * @param mixed $name
     */
    protected function unsetCalc($name): void
    {
        unset($this->zAttrsCalc[$name]);
        unset($this->zAttrsCalc['censor' . \ucfirst($name)]);

        if (isset($this->zDepend[$name])) {
            $this->zAttrsCalc = \array_diff_key($this->zAttrsCalc, \array_flip($this->zDepend[$name]));
        }
    }

    /**
     * Устанавливает значение для свойства
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, $value): void
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
     *
     * @param string $name
     * @param mixed $value
     *
     * @return Model
     */
    public function setAttr(string $name, $value): self
    {
        $this->unsetCalc($name);
        $this->zAttrs[$name] = $value;

        return $this;
    }

    /**
     * Устанавливает значения для свойств
     * Сбрасывает вычисленные свойства
     *
     * @param array $attrs
     *
     * @return Model
     */
    public function setAttrs(array $attrs): self
    {
        $this->zAttrs     = $attrs; //????
        $this->zAttrsCalc = [];

        return $this;
    }

    /**
     * Возвращает значение свойства
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (\array_key_exists($name, $this->zAttrsCalc)) {
            return $this->zAttrsCalc[$name];
        } elseif (\method_exists($this, $method = 'get' . $name)) {
            return $this->zAttrsCalc[$name] = $this->$method();
        } elseif (\array_key_exists($name, $this->zAttrs)) {
            return $this->zAttrs[$name];
        } elseif (
            0 === \strpos($name, 'censor')
            && isset($this->zAttrs[$root = \lcfirst(\substr($name, 6))])
        ) {
            return $this->zAttrsCalc[$name] = $this->c->censorship->censor($this->zAttrs[$root]);
        } else {
            return null;
        }
    }

    /**
     * Возвращает значение свойства
     * Без вычислений
     *
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    public function getAttr(string $name, $default = null)
    {
        return \array_key_exists($name, $this->zAttrs) ? $this->zAttrs[$name] : $default;
    }

    /**
     * Выполняет подгружаемый метод при его наличии
     *
     * @param string $name
     * @param array $args
     *
     * @return mixed
     */
    public function __call(string $name, array $args)
    {
        $key = \str_replace(['ForkBB\\Models\\', 'ForkBB\\', '\\'], '', \get_class($this));

        return $this->c->{$key . \ucfirst($name)}->setModel($this)->$name(...$args);
    }
}
