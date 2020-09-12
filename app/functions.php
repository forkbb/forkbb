<?php

namespace ForkBB;

use ForkBB\Core\Container;

/**
 * Инициализирует другие функции (передача контейнера)
 */
function _init(Container $c): void
{
    __(null, $c);
    dt(0, true, '', '', true, true, $c);
}

/**
 * Транслирует строку с подстановкой аргументов
 */
function __(?string $arg, /* mixed */ ...$args): string
{
    static $c;

    if (
        null === $arg
        && $args[0] instanceof Container
    ) {
        $c = $args[0];
        return '';
    }

    $tr = $c->Lang->get($arg);

    if (\is_array($tr)) {
        if (
            isset($args[0])
            && \is_int($args[0])
        ) {
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
 */
function e(string $arg): string
{
    return \htmlspecialchars($arg, \ENT_HTML5 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Возвращает число в формате текущего пользователя
 */
function num(/* mixed */ $number, int $decimals = 0): string
{
    return \is_numeric($number)
        ? \number_format($number, $decimals, __('lang_decimal_point'), __('lang_thousands_sep'))
        : '-';
}

/**
 * Возвращает дату/время в формате текущего пользователя
 */
function dt(int $arg, bool $dateOnly = false, string $dateFormat = null, string $timeFormat = null, bool $timeOnly = false, bool $noText = false, Container $container = null): string
{
    static $c;

    if (null !== $container) {
        $c = $container;
        return '';
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
 * Возвращает размер в байтах, Кбайтах, ...
 */
function size(int $size): string
{
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];

    for ($i = 0; $size > 1024; ++$i) {
        $size /= 1024;
    }

    $decimals = $size - (int) $size < 0.005 ? 0 : 2;

    return __('%s ' . $units[$i], num($size, $decimals));
}
