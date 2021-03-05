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
use function \ForkBB\__;

class Func
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Список доступных стилей
     * @var array
     */
    protected $styles;

    /**
     * Список доступных языков
     * @var array
     */
    protected $langs;

    /**
     * Список имен доступных языков
     * @var array
     */
    protected $nameLangs;

    public function __construct(Container $container)
    {
        $this->c = $container;
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
//            $pages[] = [null, 1, true];
        } else {
            if ($cur > 0) {
                $pages[] = [__($info, $cur, $all), 'info', null];
                $cur     = \min(\max(1, $cur), $all);
                if ($cur > 1) {
                    $pages[] = [
                        $this->c->Router->link(
                            $marker,
                            [
                                'page' => $cur - 1,
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
                $tpl = $all < 7
                    ? \array_slice([2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6], 0, $all - 1)
                    : [2 => 2, 3 => 3, 4 => 4, $all => $all];
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
                            'page' => $i,
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
                            'page' => $cur + 1,
                        ]
                        + $args
                    ),
                    'next',
                    null,
                ];
            }
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
            if (
                isset($dsr[1])) {
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
}
