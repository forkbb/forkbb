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
use Psr\SimpleCache\CacheInterface;
use InvalidArgumentException;
use RuntimeException;

class Lang
{
    protected CacheInterface $cache;

    /**
     * Массив переводов
     */
    protected array $tr = [];

    /**
     * Загруженные переводы
     */
    protected array $loaded = [];

    /**
     * Порядок перебора языка
     */
    protected array $langOrder = [];

    /**
     * Имя текущего загружаемого языкового файла в формате lang/name.po
     */
    protected string $cur;

    /**
     * Список операторов для вычисления Plural Forms
     */
    protected array $oprtrs = [
        '**'  => [23, true , 2], // возведение в степень
        '!'   => [20, false, 1],
        '*'   => [19, false, 2],
        '/'   => [19, false, 2],
        '%'   => [19, false, 2],
        '+'   => [18, false, 2],
        '-'   => [18, false, 2],
        '.'   => [18, false, 2],
        '<'   => [16, null , 2],
        '<='  => [16, null , 2],
        '>'   => [16, null , 2],
        '>='  => [16, null , 2],
        '=='  => [15, null , 2],
        '!='  => [15, null , 2],
        '===' => [15, null , 2],
        '!==' => [15, null , 2],
        '<>'  => [15, null , 2],
        '<=>' => [15, null , 2],
        '&'   => [14, false, 2],
        '^'   => [13, false, 2], // а это не возведение в степень!
        '|'   => [12, false, 2],
        '&&'  => [11, false, 2],
        '||'  => [10, false, 2],
        '??'  => [ 9, true , 2],
        '?'   => [ 8, true , 2], // отличие от php
        ':'   => [ 8, true , 2], // отличие от php
        'and' => [ 3, false, 2],
        'xor' => [ 2, false, 2],
        'or'  => [ 1, false, 2],
        ','   => [ 0, false, 2], // отличие от алгоритма сортировочной станции
    ];

    /**
     * Список преобразованных формул Plural Forms
     */
    protected array $pluralCashe = [];

    public function __construct(protected Container $c)
    {
        $this->cache = $c->Cache;
    }

    /**
     * Ищет сообщение в загруженных переводах
     */
    public function get(string $message, string $lang = ''): null|string|array
    {
        if (
            '' !== $lang
            && isset($this->tr[$lang][$message])
        ) {
            return $this->tr[$lang][$message];
        }

        foreach ($this->langOrder as $lang) {
            if (isset($this->tr[$lang][$message])) {
                return $this->tr[$lang][$message];
            }
        }

        return null; //$message;
    }

    /**
     * Загружает языковой файл
     */
    public function load(string $name, string $lang = '', string $path = ''): void
    {
        if ('' !== $lang) {
            // смена порядка перебора языка
            $this->langOrder = [$lang => $lang] + $this->langOrder;

            if (isset($this->loaded[$name][$lang])) {
                return;
            }
        } elseif (isset($this->loaded[$name])) {
            return;
        }

        $lang = $lang ?: $this->c->user->language;
        $path = $path ?: $this->c->DIR_LANG;

        do {
            $flag      = true;
            $this->cur = "{$lang}/{$name}.po";
            $fullPath  = "{$path}/{$this->cur}";

            if (\is_file($fullPath)) {
                $time  = \filemtime($fullPath);
                $key   = 'l_' . \sha1($fullPath);
                $cache = $this->cache->get($key);

                if (
                    isset($cache['time'], $cache['data'])
                    && $cache['time'] === $time
                ) {
                    $data = $cache['data'];
                } else {
                    $data = $this->arrayFromStr(\file_get_contents($fullPath));

                    $this->cache->set(
                        $key,
                        [
                            'time' => $time,
                            'data' => $data,
                        ]
                    );
                }

                if (isset($this->tr[$lang])) {
                    $this->tr[$lang] += $data;
                } else {
                    $this->tr[$lang]  = $data;
                }

                $this->loaded[$name][$lang] = true;
                // порядок перебора языка не изменяется
                $this->langOrder += [$lang => $lang];

                $flag = false;
            } elseif ('en' === $lang) {
                $flag = false;
            }

            $lang = 'en';
        } while ($flag);
    }

    /**
     * Получает массив перевода из строки (.po файла)
     */
    protected function arrayFromStr(string $str): array
    {
        $lines    = \explode("\n", $str);
        $count    = \count($lines);
        $result   = [];
        $cur      = [];
        $curComm  = null;
        $curVal   = '';
        $nplurals = 2;
        $plural   = '(n != 1);';

        for ($i = 0; $i < $count; ++$i) {
            $line = \trim($lines[$i]);

            // пустая строка
            if (! isset($line[0])) {
                // промежуточные данные
                if (isset($curComm)) {
                    $cur[$curComm] = $curVal;
                }

                // ошибка формата
                if (! isset($cur['msgid'])) {
                    throw new RuntimeException("File ({$this->cur}) format error");
                }

                // заголовки
                if (! isset($cur['msgid'][0])) {
                    if (\preg_match('%Plural\-Forms:\s+nplurals=(\d+);\s*plural=([^;\n\r]+)%i', $cur[0], $v)) {
                        $nplurals = (int) $v[1];
                        $plural   = \trim($v[2]);
                    }

                // перевод
                } else {
                    // множественный
                    if (
                        isset($cur['msgid_plural'][0])
                        || isset($cur[1][0])
                    ) {
                        if (! isset($cur[1][0])) {
                            $cur[1] = $cur['msgid_plural'];
                        }

                        if (! isset($cur[0][0])) {
                            $cur[0] = $cur['msgid'];
                        }

                        $curVal = [];
                        for ($v = 0; $v < $nplurals; ++$v) {
                            if (! isset($cur[$v][0])) {
                                $curVal = null;
                                break;
                            }
                            $curVal[$v] = $cur[$v];
                        }

                        if (isset($curVal)) {
                            $curVal['plural']      = $plural;
                            $result[$cur['msgid']] = $curVal;
                        }

                    // одиночный
                    } elseif (isset($cur[0])) { // [0]
                        $result[$cur['msgid']] = $cur[0];
                    }
                }

                $curComm = null;
                $curVal  = '';
                $cur     = [];

                continue;

            // комментарий
            } elseif ('#' == $line[0]) {
                continue;

            // многострочное содержимое
            } elseif ('"' == $line[0]) {
                if (isset($curComm)) {
                    $curVal .= $this->originalLine($line);
                }

                continue;

            // промежуточные данные
            } elseif (isset($curComm)) {
                $cur[$curComm] = $curVal;
            }

            // выделение команды
            $v       = \explode(' ', $line, 2);
            $command = $v[0];
            $v       = isset($v[1]) ? $this->originalLine(\trim($v[1])) : '';

            switch ($command) {
                case 'msgctxt':
                case 'msgid':
                case 'msgid_plural':
                    $curComm = $command;
                    $curVal  = $v;
                    break;

                case 'msgstr':
                case 'msgstr[0]':
                    $curComm = 0;
                    $curVal  = $v;
                    break;

                case 'msgstr[1]':
                    $curComm = 1;
                    $curVal  = $v;
                    break;

                case 'msgstr[2]':
                    $curComm = 2;
                    $curVal  = $v;
                    break;

                case 'msgstr[3]':
                    $curComm = 3;
                    $curVal  = $v;
                    break;

                case 'msgstr[4]':
                    $curComm = 4;
                    $curVal  = $v;
                    break;

                case 'msgstr[5]':
                    $curComm = 5;
                    $curVal  = $v;
                    break;

                default:
                    throw new RuntimeException("File ({$this->cur}) format error");
            }
        }

        return $result;
    }

    /**
     * Получает оригинальную строку с удалением кавычек
     * и преобразованием спецсимволов
     */
    protected function originalLine(string $line): string
    {
        if (
            isset($line[1])
            && '"' == $line[0]
            && '"' == $line[-1]
        ) {
            $line = \substr($line, 1, -1);
        }

        return \str_replace(
            ['\\n', '\\t', '\\"', '\\\\'],
            ["\n",  "\t",  '"',  '\\'],
            $line
        );
    }

    /**
     * Разбивает мат./лог. выражение на токены
     */
    protected function getTokenList(string $expression): array
    {
        \preg_match_all('%[(),]|\b[\w.]+\b|[^\s\w(),.]+%', $expression, $matches);

        return $matches[0];
    }

    /**
     * Преобразовывает токены из infix порядка в postfix
     * Есть отличия от алгоритма сортировочной станции
     */
    protected function infixToPostfix(array $infixList): array
    {
        $postfix = [];
        $stack   = [];
        $any     = null;

        foreach ($infixList as $token) {
            if (isset($any)) {
                if ('(' === $token) {
                    // функция
                    $stack[] = "$any()";
                } else {
                    // переменная
                    $postfix[] = $any;
                }

                $any = null;
            }

            // оператор
            if (isset($this->oprtrs[$token])) {
                while (
                    false !== ($peek = \end($stack))
                    && isset($this->oprtrs[$peek])
                    && (
                        $this->oprtrs[$peek][0] > $this->oprtrs[$token][0]
                        || (
                            false === $this->oprtrs[$token][1]
                            && $this->oprtrs[$peek][0] == $this->oprtrs[$token][0]
                        )
                    )
                ) {
                    $postfix[] = \array_pop($stack);
                }

                $stack[] = $token;

            // открывающая скобка
            } elseif ('(' === $token) {
                $stack[] = $token;

            // закрывающая скобка
            } elseif (')' === $token) {
                while ($peek = \array_pop($stack)) {
                    // стек до ( переложить в postfix
                    if ('(' !== $peek) {
                        $postfix[] = $peek;
                    } else {
                        // переложить функцию в postfix
                        if (
                            \is_string($peek = \end($stack))
                            && isset($peek[2])
                            && ')' === $peek[-1]
                        ) {
                            $postfix[] = \array_pop($stack);
                        }

                        continue 2;
                    }
                }

                throw new RuntimeException('Missing open parenthesis');

            // числа, переменные, функции
            } else {
                $trim = \trim($token, '1234567890');

                if ('' === $trim) {
                    $postfix[] = (int) $token;
                } elseif ('.' === $trim) {
                    $postfix[] = (float) $token;
                } else {
                    // то ли функция, то ли переменная
                    $any = $token;
                }
            }
        }

        if (isset($any)) {
            $postfix[] = $any;
        }

        while ($peek = \array_pop($stack)) {
            if ('(' === $peek) {
                throw new RuntimeException('Missing close parenthesis');
            }

            $postfix[] = $peek;
        }

        return $postfix;
    }

    /**
     * Вычисляет выражение представленное токенами в postfix записи и переменными
     */
    protected function calcPostfix(array $postfixList, array $vars = []): mixed
    {
        foreach ($postfixList as $token) {
            if (\is_string($token)) {
                if (isset($this->oprtrs[$token])) {
                    switch ($this->oprtrs[$token][2]) {
                        case 2:
                            $v2 = \array_pop($stack);

                            if (null === $v2) {
                                throw new RuntimeException('Unexpected end of operand stack');
                            }
                        case 1:
                            $v1 = \array_pop($stack);

                            if (null === $v2) {
                                throw new RuntimeException('Unexpected end of operand stack');
                            }

                            break;
                        default:
                            throw new RuntimeException('Action expected with 1 or 2 operands: ' . $token);
                    }
                }

                switch ($token) {
                    case '+'   : $stack[] = $v1 + $v2; break;
                    case '-'   : $stack[] = $v1 - $v2; break;
                    case '*'   : $stack[] = $v1 * $v2; break;
                    case '/'   : $stack[] = $v1 / $v2; break;
                    case '%'   : $stack[] = $v1 % $v2; break;
                    case '.'   : $stack[] = $v1 . $v2; break;
                    case '**'  : $stack[] = $v1 ** $v2; break;
                    case '!'   : $stack[] = ! $v1; break;
                    case '<'   : $stack[] = $v1 < $v2; break;
                    case '<='  : $stack[] = $v1 <= $v2; break;
                    case '>'   : $stack[] = $v1 > $v2; break;
                    case '>='  : $stack[] = $v1 >= $v2; break;
                    case '=='  : $stack[] = $v1 == $v2; break;
                    case '!='  : $stack[] = $v1 != $v2; break;
                    case '===' : $stack[] = $v1 === $v2; break;
                    case '!==' : $stack[] = $v1 !== $v2; break;
                    case '<>'  : $stack[] = $v1 <> $v2; break;
                    case '<=>' : $stack[] = $v1 <=> $v2; break;
                    case '&'   : $stack[] = $v1 & $v2; break;
                    case '^'   : $stack[] = $v1 ^ $v2; break;
                    case '|'   : $stack[] = $v1 | $v2; break;
                    case '&&'  : $stack[] = $v1 && $v2; break;
                    case '||'  : $stack[] = $v1 || $v2; break;
                    case 'and' : $stack[] = $v1 and $v2; break;
                    case 'xor' : $stack[] = $v1 xor $v2; break;
                    case 'or'  : $stack[] = $v1 or $v2; break;
                    case '??'  : $stack[] = $v1 ?? $v2; break;
                    case '?'   : $stack[] = $v2[$v1 ? 'T' : 'F']; break;
                    case ':'   : $stack[] = ['T' => $v1, 'F' => $v2]; break;
                    case ','   :
                        // собрать аргументы функции в массив
                        if (\is_array($v1)) {
                            $v1[]    = $v2;
                            $stack[] = $v1;
                        } else {
                            $stack[] = [$v1, $v2];
                        }

                        break;
                    default:
                        // подстановка переменной
                        if (isset($vars[$token])) {
                            $stack[] = $vars[$token];

                            break;
                        }

                        throw new RuntimeException('Unexpected operation: ' . $token);
                }
            } else {
                $stack[] = $token;
            }
        }

        if (1 !== \count($stack)) {
            throw new RuntimeException('1 operand-result should remain on the stack, there are: ' . \count($stack));
        }

        return \array_pop($stack);
    }

    /**
     * Возвращает вариант перевода
     */
    public function getForm(array $pluralForms, int $number): string
    {
        if (! isset($pluralForms['plural'])) {
            throw new InvalidArgumentException('Plural Forms missing \'plural\' element');
        }

        $plural = \str_replace('$n', 'n', \trim($pluralForms['plural'], "; \n\r\t\v\0")); // fix старого формата от eval()

        if (! isset($this->pluralCashe[$plural])) {
            $this->pluralCashe[$plural] = $this->infixToPostfix($this->getTokenList($plural));
        }

        $option = $this->calcPostfix($this->pluralCashe[$plural], ['n' => $number]);

        return $pluralForms[$option];
    }
}
