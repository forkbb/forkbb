<?php

namespace ForkBB\Core;

use ForkBB\Models\Model;

class FuncAll
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Модель вызова
     * @var Model
     */
    protected $model;

    /**
     * Метод вызова
     * @var string
     */
    protected $method;

    /**
     * Аргументы вызова
     * @var array
     */
    protected $args;

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
     * Настройка вызова
     * 
     * @param string $method
     * @param Model $model
     * @param array ...$args
     * 
     * @return FuncAll
     */
    public function setModel($method, Model $model, ...$args) 
    {
        $this->model  = $model;
        $this->method = $method;
        $this->args   = $args;
        return $this;
    }

    /**
     * Обработака вызова
     * 
     * @param string $name
     * 
     * @return mixed
     */
    public function __get($name)
    {
        $data = $this->model->{$name};

        return $this->{$this->method}($data, ...$this->args);
    }

    /**
     * Цензура
     * 
     * @param string $str
     * 
     * @return string
     */
    public function cens($str)
    {
        return $this->c->censorship->censor($str);
    }

    /**
     * Возвращает число в формате языка текущего пользователя
     *
     * @param mixed $number
     * @param int $decimals
     *
     * @return string
     */
    protected function num($number, $decimals = 0)
    {
        return is_numeric($number)
            ? number_format($number, $decimals, __('lang_decimal_point'), __('lang_thousands_sep'))
            : 'not a number';
    }

    /**
     * Возвращает даты/время в формате текущего пользователя
     *
     * @param int $timestamp
     * @param bool $dateOnly
     * @param string $dateFormat
     * @param string $timeFormat
     * @param bool $timeOnly
     * @param bool $noText
     *
     * @return string
     */
    protected function dt($timestamp, $dateOnly = false, $dateFormat = null, $timeFormat = null, $timeOnly = false, $noText = false)
    {
        if (empty($timestamp)) {
            return __('Never');
        }

        $user = $this->c->user;

        $diff = ($user->timezone + $user->dst) * 3600;
        $timestamp += $diff;

        if (null === $dateFormat) {
            $dateFormat = $this->c->DATE_FORMATS[$user->date_format];
        }
        if(null === $timeFormat) {
            $timeFormat = $this->c->TIME_FORMATS[$user->time_format];
        }

        $date = gmdate($dateFormat, $timestamp);

        if(! $noText) {
            $now = time() + $diff;

            if ($date == gmdate($dateFormat, $now)) {
                $date = __('Today');
            } elseif ($date == gmdate($dateFormat, $now - 86400)) {
                $date = __('Yesterday');
            }
        }

        if ($dateOnly) {
            return $date;
        } elseif ($timeOnly) {
            return gmdate($timeFormat, $timestamp);
        } else {
            return $date . ' ' . gmdate($timeFormat, $timestamp);
        }
    }

    /**
     * Преобразует timestamp в YYYY-MM-DDTHH:mm:ss.sssZ
     * 
     * @param int $timestamp
     * 
     * @return string
     */
    public function utc($timestamp)
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
