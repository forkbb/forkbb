<?php

namespace ForkBB\Models;

use ForkBB\Models\Model;
use InvalidArgumentException;
use RuntimeException;

class DataModel extends Model
{
    /**
     * Массив флагов измененных свойств модели
     * @var array
     */
    protected $zModFlags = [];

    /**
     * Массив состояний отслеживания изменений в свойствах модели
     * @var array
     */
    protected $zTrackFlags = [];

    /**
     * Устанавливает значения для свойств
     * Сбрасывает вычисленные свойства
     * Флаги модификации свойст сброшены
     *
     * @param array $attrs
     *
     * @return DataModel
     */
    public function setAttrs(array $attrs): self
    {
        $this->zModFlags   = [];
        $this->zTrackFlags = [];

        return parent::setAttrs($attrs);
    }

    /**
     * Перезаписывает свойства модели
     * Флаги модификации свойств сбрасываются/устанавливаются в зависимости от второго параметра
     *
     * @param array $attrs
     * @param bool $setFlags
     *
     * @return DataModel
     */
    public function replAttrs(array $attrs, bool $setFlags = false): self
    {
        foreach ($attrs as $name => $value) {
            $this->__set($name, $value);

            if (! $setFlags) {
                unset($this->zModFlags[$name]);
            }
        }

        return $this;
    }

    /**
     * Возвращает значения свойств в массиве
     *
     * @return array
     */
    public function getAttrs(): array
    {
        return $this->zAttrs; //????
    }

    /**
     * Возвращает массив имен измененных свойств модели
     *
     * @return array
     */
    public function getModified(): array
    {
        return \array_keys($this->zModFlags);
    }

    /**
     * Обнуляет массив флагов измененных свойств модели
     */
    public function resModified(): void
    {
        $this->zModFlags   = [];
        $this->zTrackFlags = [];
    }

    /**
     * Устанавливает значение для свойства
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        // без отслеживания
        if (0 === \strpos($name, '__')) {
            $track = null;
            $name  = \substr($name, 2);
        // с отслеживанием
        } else {
            $track = false;
            if (\array_key_exists($name, $this->zAttrs)) {
                $track = true;
                $old   = $this->zAttrs[$name];
                // fix
                if (
                    \is_int($value)
                    && \is_numeric($old)
                    && \is_int(0 + $old)
                ) {
                    $old = (int) $old;
                }
            }
        }

        $this->zTrackFlags[$name] = $track;

        parent::__set($name, $value);

        unset($this->zTrackFlags[$name]);

        if (null === $track) {
            return;
        }

        if (
            (
                ! $track
                && \array_key_exists($name, $this->zAttrs)
            )
            || (
                $track
                && $old !== $this->zAttrs[$name]
            )
        ) {
            $this->zModFlags[$name] = true;
        }
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
        // без вычисления
        if (0 === \strpos($name, '__')) {
            return $this->getAttr(\substr($name, 2));
        // с вычислениями
        } else {
            return parent::__get($name);
        }
    }
}
