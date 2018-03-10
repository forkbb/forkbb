<?php

namespace ForkBB;

use ForkBB\Core\Container;

/**
 * Инициализирует другие функции (передача контейнера)
 *
 * @param Container $c
 */
function _init(Container $c)
{
    __($c);
    cens($c);
    dt($c);
}

/**
 * Транслирует строку с подстановкой аргументов
 *
 * @param Container|string $arg
 * @param mixed ...$args
 *
 * @return string
 */
function __($arg, ...$args)
{
    static $c;

    if (empty($c)) {
        $c = $arg;
        return;
    }

    $tr = $c->Lang->get($arg);

    if (\is_array($tr)) {
        if (isset($args[0]) && \is_int($args[0])) {
            $n = \array_shift($args);
            eval('$n = (int) ' . $tr['plural']);
            $tr = $tr[$n];
        } else {
            $tr = $tr[0];
        }
    }

    if (empty($args)) {
        return $tr;
    } elseif (\is_array($args[0])) {
        return \strtr($tr, \array_map('\ForkBB\e', $args[0]));
    } else {
        $args = \array_map('\ForkBB\e', $args);
        return \sprintf($tr, ...$args);
    }
}

/**
 * Экранирует спецсимволов HTML-сущностями
 *
 * @param  string $arg
 *
 * @return string
 */
function e($arg)
{
    return \htmlspecialchars($arg, \ENT_HTML5 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Цензура
 *
 * @param Container|string $arg
 *
 * @return string
 */
function cens($arg)
{
    static $c;

    if (empty($c)) {
        $c = $arg;
        return;
    }

    return $c->censorship->censor($arg);
}

/**
 * Возвращает число в формате текущего пользователя
 *
 * @param mixed $number
 * @param int $decimals
 *
 * @return string
 */
function num($number, $decimals = 0)
{
    return \is_numeric($number)
        ? \number_format($number, $decimals, __('lang_decimal_point'), __('lang_thousands_sep'))
        : 'not a number';
}

/**
 * Возвращает дату/время в формате текущего пользователя
 *
 * @param Container|int $arg
 * @param bool $dateOnly
 * @param string $dateFormat
 * @param string $timeFormat
 * @param bool $timeOnly
 * @param bool $noText
 *
 * @return string
 */
function dt($arg, $dateOnly = false, $dateFormat = null, $timeFormat = null, $timeOnly = false, $noText = false)
{
    static $c;

    if (empty($c)) {
        $c = $arg;
        return;
    }

    if (empty($arg)) {
        return __('Never');
    }

    $diff = ($c->user->timezone + $c->user->dst) * 3600;
    $arg += $diff;

    if (null === $dateFormat) {
        $dateFormat = $c->DATE_FORMATS[$c->user->date_format];
    }
    if(null === $timeFormat) {
        $timeFormat = $c->TIME_FORMATS[$c->user->time_format];
    }

    $date = \gmdate($dateFormat, $arg);

    if(! $noText) {
        $now = \time() + $diff;

        if ($date == \gmdate($dateFormat, $now)) {
            $date = __('Today');
        } elseif ($date == \gmdate($dateFormat, $now - 86400)) {
            $date = __('Yesterday');
        }
    }

    if ($dateOnly) {
        return $date;
    } elseif ($timeOnly) {
        return \gmdate($timeFormat, $arg);
    } else {
        return $date . ' ' . \gmdate($timeFormat, $arg);
    }
}

/**
 * Преобразует timestamp в YYYY-MM-DDTHH:mm:ss.sssZ
 *
 * @param int $timestamp
 *
 * @return string
 */
function utc($timestamp)
{
    return \gmdate('c', $timestamp); // Y-m-d\TH:i:s\Z
}

/**
 * Возвращает размер в байтах, Кбайтах, ...
 *
 * @param int $size
 *
 * @return string
 */
function size($size)
{
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];

    for ($i = 0; $size > 1024; ++$i) {
        $size /= 1024;
    }

    return __('%s ' . $units[$i], num($size, 2)); // \sprintf('%.2f', $size)
}
