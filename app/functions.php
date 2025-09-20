<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB;

use ForkBB\Core\Container;
use IntlDateFormatter;
use InvalidArgumentException;

\error_reporting(\E_ALL ^ \E_NOTICE);
\ini_set('display_errors', '0');
\ini_set('log_errors', '1');

define('FORK_START', $_SERVER['REQUEST_TIME_FLOAT'] ?? \microtime(true));
define('FORK_REVISION', 89);

\setlocale(\LC_ALL, 'C');
\mb_language('uni');
\mb_internal_encoding('UTF-8');
\mb_substitute_character(0xFFFD);

define('FORK_GROUP_UNVERIFIED', 0);
define('FORK_GROUP_ADMIN', 1);
define('FORK_GROUP_MOD', 2);
define('FORK_GROUP_GUEST', 3);
define('FORK_GROUP_MEMBER', 4);

define('FORK_MESS_INFO', 'i');
define('FORK_MESS_SUCC', 's');
define('FORK_MESS_WARN', 'w');
define('FORK_MESS_ERR',  'e');
define('FORK_MESS_VLD',  'v');

define('FORK_GEN_NOT', 0);
define('FORK_GEN_MAN', 1);
define('FORK_GEN_FEM', 2);

define('FORK_JSON_ENCODE', \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
define('FORK_SFID', 2147483647);
define('FORK_CTYPE', $_SERVER['CONTENT_TYPE'] ?? '');
define('FORK_ADDR', $_SERVER['REMOTE_ADDR'] ?? '');
define('FORK_RMETHOD', $_SERVER['REQUEST_METHOD'] ?? '');
define('FORK_URI', $_SERVER['REQUEST_URI'] ?? '');
define('FORK_ACC', $_SERVER['HTTP_ACCEPT'] ?? '');
define('FORK_ENC', $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
define('FORK_LNG', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
define('FORK_REF', $_SERVER['HTTP_REFERER'] ?? '');
define('FORK_UA', $_SERVER['HTTP_USER_AGENT'] ?? '');

/**
 * Инициализирует другие функции (передача контейнера)
 */
function _init(Container $c): void
{
    __([$c]);
    dt(0, null, null, false, $c);
}

/**
 * Переводит строку с подстановкой аргументов
 * Защита от дурака отсутствует, ловим ошибки/исключения
 */
function __(string|array $arg): string
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
function num(mixed $number, int $decimals = 0): string
{
    return \is_numeric($number)
        ? \number_format((float) $number, $decimals, __('lang_decimal_point'), __('lang_thousands_sep'))
        : '-';
}

/**
 * Возвращает дату/время в формате текущего пользователя
 */
function dt(int $arg, ?int $dateType = null, ?int $timeType = null, bool $noText = false, ?Container $container = null): string
{
    static $c, $idfs = [],
        $types = [
            0 => IntlDateFormatter::NONE,
            1 => IntlDateFormatter::SHORT,
            2 => IntlDateFormatter::MEDIUM,
            3 => IntlDateFormatter::LONG,
            4 => IntlDateFormatter::FULL,
        ],
        $typesR = [
            0 => IntlDateFormatter::NONE,
            1 => IntlDateFormatter::RELATIVE_SHORT,
            2 => IntlDateFormatter::RELATIVE_MEDIUM,
            3 => IntlDateFormatter::RELATIVE_LONG,
            4 => IntlDateFormatter::RELATIVE_FULL,
        ];

    if (0 === $arg) {
        if (null !== $container) {
            $c = $container;

            return '';

        } else {
            return __('Never');
        }
    }

    $dateType ??= \min(4, \max(1, $c->user->date_format));
    $timeType ??= \min(4, \max(1, $c->user->time_format));

    $key = "i,{$noText},{$dateType},{$timeType}";

    if (! isset($idfs[$key])) {
        $idfs[$key] = new IntlDateFormatter($c->user->locale, $noText ? $types[$dateType] : $typesR[$dateType], $types[$timeType], $c->user->timezone);
    }

    return $idfs[$key]->format($arg);
}

/**
 * Возвращает размер в байтах, Кбайтах, ...
 */
function size(int $size): string
{
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];

    for ($i = 0; $size >= 1024; ++$i) {
        $size /= 1024;
    }

    $decimals = $size - (int) $size < 0.005 ? 0 : 2;

    return __(['%s ' . $units[$i], num($size, $decimals)]);
}

/**
 * Возвращает нормализованный (частично) url или пустую строку
 */
function url(string $url): string
{
    if (
        ! isset($url[1])
        || \preg_match('%^\s%u', $url)
    ) {
        return '';
    }

    switch ($url[0]) {
        case '/':
            if ('/' === $url[1]) {
                $schemeOn = false;
                $hostOn   = true;
                $url      = 'http:' . $url;

                break;
            }
        case '?':
        case '#':
            $schemeOn = false;
            $hostOn   = false;
            $url      = 'http://a.a' . $url;

            break;
        default:
            $hostOn = true;

            if (\preg_match('%^([a-z][a-z0-9+.-]*):(\S)?%i', $url, $m)) {
                if (
                    ! isset($m[2])
                    || isset($m[1][10])
                ) {
                    return '';
                }

                $schemeOn = true;

            } else {
                $schemeOn = false;
                $url      = 'http://' . $url;
            }

            break;
    }

    $p = \parse_url($url);

    if (! \is_array($p)) {
        return '';
    }

    $scheme = \strtolower($p['scheme'] ?? '');
    $result = $schemeOn && $scheme ? $scheme . ':' : '';

    switch ($scheme) {
        case 'javascript':
            return '';
        case 'mailto':
            if (
                isset($p['host'])
                || ! isset($p['path'])
                || ! \preg_match('%^([^\x00-\x1F]+)@([^\x00-\x1F\s@]++)$%Du', $p['path'], $m)
            ) {
                return '';
            }

            $result .= \rawurlencode(\rawurldecode($m[1])) . '@' . \rawurlencode(\rawurldecode($m[2]));

            break;
        case 'tel':
            if (
                isset($p['host'])
                || ! isset($p['path'])
                || ! \preg_match('%^\+?[0-9.()-]+$%D', $p['path'])
            ) {
                return '';
            }

            $result .= $p['path'];

            break;
        default:
            if ($hostOn && isset($p['host'])) {
                $result .= '//';

                if (isset($p['user'])) {
                    $result .= \rawurlencode(\rawurldecode($p['user']));

                    if (isset($p['pass'])) {
                        $result .= ':' . \rawurlencode(\rawurldecode($p['pass']));
                    }

                    $result .= '@';
                }

                if (\preg_match('%[\x80-\xFF]%', $p['host'])) {
                    $p['host'] = \idn_to_ascii($p['host'], 0, \INTL_IDNA_VARIANT_UTS46);
                }

                $host = \filter_var($p['host'], \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME);

                if (\is_string($host)) {
                    $result .= $host;

                } elseif (
                    isset($p['host'][1])
                    && '[' === $p['host'][0]
                    && ']' === $p['host'][-1]
                    && \filter_var(\substr($p['host'], 1, -1), \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)
                ) {
                    $result .= $p['host'];

                } else {
                    return '';
                }

                if (isset($p['port'])) {
                    $result .= ':' . $p['port'];
                }
            }

            if (isset($p['path'])) {
                $result .= \preg_replace_callback(
                    '%[^/\%\w.~-]|\%(?![0-9a-fA-F]{2})%',
                    function($m) {
                        return \rawurlencode($m[0]);
                    },
                    $p['path']
                );
            }

            break;
    }

    if (isset($p['query'])) {
        $result .= '?' . \preg_replace_callback(
            '%[^=&\%\w.~-]|\%(?![0-9a-fA-F]{2})%',
            function($m) {
                return \rawurlencode($m[0]);
            },
            $p['query']
        );
    }

    if (isset($p['fragment'])) {
        $result .= '#' . \preg_replace_callback(
            '%[^\%\w.~-]|\%(?![0-9a-fA-F]{2})%',
            function($m) {
                return \rawurlencode($m[0]);
            },
            $p['fragment']
        );
    }

    return $result;
}
