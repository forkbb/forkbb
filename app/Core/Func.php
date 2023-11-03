<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Container;
use DateTime;
use DateTimeZone;
use Transliterator;
use function \ForkBB\__;

class Func
{
    /**
     * Список доступных стилей
     */
    protected ?array $styles = null;

    /**
     * Список доступных языков
     */
    protected ?array $langs = null;

    /**
     * Список имен доступных языков
     */
    protected ?array $nameLangs = null;

    /**
     * Смещение времени для текущего пользователя
     */
    protected ?int $offset = null;

    /**
     * Копия $this->c->FRIENDLY_URL
     */
    protected array $fUrl;

    public function __construct(protected Container $c)
    {
        $this->fUrl = $this->c->FRIENDLY_URL;
    }

    /**
     * Список доступных стилей
     */
    public function getStyles(): array
    {
        if (! \is_array($this->styles)) {
            $this->styles = $this->getFoldersWithFile($this->c->DIR_PUBLIC . '/style', 'style.css');
        }

        return $this->styles;
    }

    /**
     * Список доступных языков
     */
    public function getLangs(): array
    {
        if (! \is_array($this->langs)) {
            $this->langs = $this->getFoldersWithFile($this->c->DIR_LANG, 'common.po');
        }

        return $this->langs;
    }

    /**
     * Список имен доступных языков
     */
    public function getNameLangs(): array
    {
        if (! \is_array($this->nameLangs)) {
            $langs = $this->getLangs();

            foreach ($langs as &$value) {
                $value = include "{$this->c->DIR_LANG}/{$value}/name.php";
            }

            unset($value);

            $this->nameLangs = $langs;
        }

        return $this->nameLangs;
    }

    /**
     * Список папок в данной директории содержащих заданный файл
     */
    public function getFoldersWithFile(string $dir, string $file): array
    {
        $result = [];
        if (
            \is_dir($dir)
            && false !== ($dh = \opendir($dir))
        ) {
            while (false !== ($entry = \readdir($dh))) {
                if (
                    isset($entry[0])
                    && '.' !== $entry[0]
                    && \is_dir("{$dir}/{$entry}")
                    && \is_file("{$dir}/{$entry}/{$file}")
                ) {
                    $result[$entry] = $entry;
                }
            }

            \closedir($dh);
            \asort($result, \SORT_NATURAL);
        }

        return $result;
    }

    /**
     * Пагинация
     */
    public function paginate(int $all, int $cur, string $marker, array $args = [], string $info = 'Page %1$s of %2$s'): array
    {
        $pages = [];

        if ($all < 2) {
            return $pages;
        }

        // нестандарная переменная для page
        if (isset($args['page'])) {
            if (\is_string($args['page'])) {
                $pn = $args['page'];

                unset($args[$pn]);
            } else {
                $pn = 'page';
            }

            unset($args['page']);
        } else {
            $pn = 'page';
        }

        if ($cur > 0) {
            $pages[] = [[$info, $cur, $all], 'info', null];
            $cur     = \min(\max(1, $cur), $all);

            if ($cur > 1) {
                $i       = $cur - 1;
                $pages[] = [
                    $this->c->Router->link(
                        $marker,
                        [
                            $pn => $i > 1 ? $i : null,
                        ]
                        + $args
                    ),
                    'prev',
                    null,
                ];
            }

            $tpl   = [1 => 1];
            $start = $cur < 6 ? 2 : $cur - 2;
            $end   = $all - $cur < 5 ? $all : $cur + 3;

            for ($i = $start; $i < $end; ++$i) {
                $tpl[$i] = $i;
            }

            $tpl[$all] = $all;
        } else {
            $tpl = [];

            if ($all > 999) {
                $d = 2;
            } elseif ($all > 99) {
                $d = 3;
            } else {
                $d = \min(4, $all - 2);
            }

            for ($i = $all - $d; $i <= $all; $i++) {
                $tpl[$i] = $i;
            }
        }

        $k = 1;

        foreach ($tpl as $i) {
            if ($i - $k > 1) {
                $pages[] = [null, 'space', null];
            }

            $pages[] = [
                $this->c->Router->link(
                    $marker,
                    [
                        $pn => $i > 1 ? $i : null,
                    ]
                    + $args
                ),
                $i,
                $i === $cur ? true : null,
            ];
            $k = $i;
        }

        if (
            $cur > 0
            && $cur < $all
        ) {
            $pages[] = [
                $this->c->Router->link(
                    $marker,
                    [
                        $pn => $cur + 1,
                    ]
                    + $args
                ),
                'next',
                null,
            ];
        }

        return $pages;
    }

    /**
     * Разбор HTTP_ACCEPT_LANGUAGE
     */
    public function langParse(string $str): array
    {
        $result = [];

        foreach (\explode(',', $str) as $step) {
            $dsr = \explode(';', $step, 2);

            if (isset($dsr[1])) {
                $q = \trim(\ltrim(\ltrim($dsr[1], 'q '), '='));

                if (
                    ! \is_numeric($q)
                    || $q < 0
                    || $q > 1
                ) {
                    continue;
                }

                $q = (float) $q;
            } else {
                $q = 1;
            }

            $l = \trim($dsr[0]);

            if (\preg_match('%^[[:alpha:]]{1,8}(?:-[[:alnum:]]{1,8})?$%', $l)) {
                $result[$l] = $q;
            }
        }

        \arsort($result, \SORT_NUMERIC);

        return \array_keys($result);
    }

    /**
     * Возвращает смещение в секундах для часовой зоны текущего пользователя или 0
     */
    public function offset(): int
    {
        if (null !== $this->offset) {
            return $this->offset;
        } elseif (\in_array($this->c->user->timezone, DateTimeZone::listIdentifiers(), true)) {
            $dateTimeZone = new DateTimeZone($this->c->user->timezone);
            $dateTime     = new DateTime('now', $dateTimeZone);

            return $this->offset = $dateTime->getOffset();
        } else {
            return $this->offset = 0;
        }
    }

    /**
     * Переводит метку времени в дату-время с учетом/без учета часового пояса пользователя
     */
    public function timeToDate(int $timestamp, bool $useOffset = true): string
    {
        return \gmdate('Y-m-d\TH:i:s', $timestamp + ($useOffset ? $this->offset() : 0));
    }

    /**
     * Переводит дату-время в метку времени с учетом/без учета часового пояса пользователя
     */
    public function dateToTime(string $date, bool $useOffset = true): int|false
    {
        $timestamp = \strtotime("{$date} UTC");

        if (! \is_int($timestamp)) {
            return false;
        } elseif ($useOffset) {
            return $timestamp - $this->offset();
        } else {
            return $timestamp;
        }
    }

    /**
     * Для кэширования транслитератора
     */
    protected Transliterator|false|null $transl = null;

    /**
     * Преобразует строку в соотвествии с правилами FRIENDLY_URL
     */
    public function friendly(string $str): string
    {
        if (null === $this->transl) {
            if (empty($this->fUrl['translit'])) {
                $this->transl = false;
            } else {
                $this->transl = Transliterator::create($this->fUrl['translit']) ?? false;
            }
        }

        if ($this->transl instanceof Transliterator) {
            $str = $this->transl->transliterate($str);
        }

        if (true === $this->fUrl['lowercase']) {
            $str = \mb_strtolower($str, 'UTF-8');
        }

        if (true === $this->fUrl['WtoHyphen']) {
            $str = \trim(\preg_replace(['%[^\w]+%u', '%_+%'], ['-', '_'], $str), '-_');
        }

        return isset($str[0]) ? $str : '-';
    }
}
