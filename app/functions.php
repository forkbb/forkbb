<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB;

use ForkBB\Core\Container;
use InvalidArgumentException;

/**
 * Инициализирует другие функции (передача контейнера)
 */
function _init(Container $c): void
{
    __([$c]);
    dt(0, true, '', '', true, true, $c);
}

/**
 * Переводит строку с подстановкой аргументов
 * Защита от дурака отсутствует, ловим ошибки/исключения
 */
function __(/* string|arrray */ $arg): string
{
    static $c, $lang;

    if (null === $lang) {
        if (null === $c) {
            $c = \reset($arg);

            if (! $c instanceof Container) {
                throw new InvalidArgumentException('Container expected');
            }

            return '';
        } else {
            $lang = $c->Lang;
        }
    }

    if (\is_array($arg)) {
        $str = \array_shift($arg);
        $tr  = $lang->get($str);

        if (null === $tr) {
            $tr = e($str);
        } elseif (\is_array($tr)) {
            $num = \array_shift($arg);
            $tr  = $lang->getForm($tr, $num);
        }

        if (empty($arg)) {
            return $tr;
        } elseif (\is_array(\reset($arg))) {
            return \strtr($tr, \array_map('\\ForkBB\\e', \reset($arg)));
        } else {
            $arg = \array_map('\\ForkBB\\e', $arg);
            return \sprintf($tr, ...$arg);
        }
    } else {
        return $lang->get($arg) ?? e($arg);
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
        ? \number_format((float) $number, $decimals, __('lang_decimal_point'), __('lang_thousands_sep'))
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

    $diff = (int) (($c->user->timezone + $c->user->dst) * 3600);
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

    return __(['%s ' . $units[$i], num($size, $decimals)]);
}
