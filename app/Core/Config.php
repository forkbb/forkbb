<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Exceptions\ForkException;

class Config
{
    /**
     * Путь до файла конфига
     */
    protected string $path;

    /**
     * Содержимое файла конфига
     */
    protected string $fileContents;

    /**
     * Начальная позиция массива конфига
     */
    protected int $arrayStartPos;

    /**
     * Массив токенов
     */
    protected array $tokens;

    /**
     * Текущая позиция в массиве токенов
     */
    protected int $position;

    /**
     * Массив полученый из файла настройки путем его парсинга
     */
    protected array $configArray;

    /**
     * Строка массива конфига в файле конфигурации
     */
    protected string $configStr;

    public function __construct(string $path)
    {
        if (! \is_file($path)) {
            throw new ForkException('Config not found');
        }
        if (! \is_readable($path)) {
            throw new ForkException('Config can not be read');
        }
        if (! \is_writable($path)) {
            throw new ForkException('Config can not be write');
        }

        $this->fileContents = \file_get_contents($path);
        $this->path         = $path;

        if (\preg_match('%\[\s*\'BASE_URL\'\s+=>%s', $this->fileContents, $matches, \PREG_OFFSET_CAPTURE)) {
            $this->arrayStartPos = $matches[0][1];
            $this->configArray   = $this->getArray();

            return;
        }

        throw new ForkException('The structure of the config file is undefined');
    }

    /**
     * Получает массив настроек из файла конфига
     */
    protected function getArray(): array
    {
        if (
            false === \preg_match_all(
                '%
                    //[^\r\n]*+
                |
                    \#[^\r\n]*+
                |
                    /\*.*?\*/
                |
                    \'.*?(?<!\\\\)\'
                |
                    ".*?(?<!\\\\)"
                |
                    \s+
                |
                    \[
                |
                    \]
                |
                    ,
                |
                    =>
                |
                    function\s*\(.+?\)\s*\{.*?\}(?=,)
                |
                    (?:\\\\)?[\w-]+\s*\(.+?\)(?=,)
                |
                    \S+(?<![,\]\)])
                %sx',
                \substr($this->fileContents, $this->arrayStartPos),
                $matches
            )
            || empty($matches)
        ) {
            throw new ForkException('Config array cannot be parsed (1)');
        }

        $this->tokens    = $matches[0];
        $this->position  = 0;
        $this->configStr = '';

        return $this->parse('ZERO');
    }

    /**
     * Очищает ключ от кавычек
     */
    protected function clearKey(mixed $key): string
    {
        if (! \is_string($key)) {
            throw new ForkException('Config array cannot be parsed (2)');
        }

        if ((
                '\'' === $key[0]
                && \strlen($key) > 1
                && '\'' === $key[-1]
            )
            || (
                '"' === $key[0]
                && \strlen($key) > 1
                && '"' === $key[-1]
            )
        ) {
            return \substr($key, 1, -1);
        }

        return $key;
    }

    /**
     * Создает массив конфига из токенов (массива подстрок)
     */
    protected function parse(string $type): array
    {
        $result       = [];
        $value        = null;
        $key          = null;
        $other        = '';
        $value_before = '';
        $value_after  = '';
        $key_before   = '';
        $key_after    = '';

        while (isset($this->tokens[$this->position])) {
            $token            = $this->tokens[$this->position];
            $this->configStr .= $token;

            // открытие массива
            if ('[' === $token) {
                switch ($type) {
                    case 'ZERO':
                        $type = 'NEW';
                        break;
                    case 'NEW':
                    case '=>':
                        $this->configStr = \substr($this->configStr, 0, -1);
                        $value           = $this->parse('ZERO');
                        $value_before    = $other;
                        $other           = '';
                        $type            = 'VALUE';
                        break;
                    default:
                        throw new ForkException('Config array cannot be parsed (3)');
                }

            // закрытие массива
            } elseif (']' === $token) {
                switch ($type) {
                    case 'NEW':
                    case 'VALUE':
                    case 'VALUE_OR_KEY':
                        if (null !== $value) {
                            $value = [
                                'value'        => $value,
                                'value_before' => $value_before,
                                'value_after'  => $other,
                                'key_before'   => $key_before,
                                'key_after'    => $key_after,
                            ];

                            if (null !== $key) {
                                $result[$this->clearKey($key)] = $value;
                            } else {
                                $result[] = $value;
                            }
                        } elseif (null !== $key) {
                            throw new ForkException('Config array cannot be parsed (4)');
                        }

                        return $result;
                    default:
                        throw new ForkException('Config array cannot be parsed (5)');
                }
            // новый элемент
            } elseif (',' === $token) {
                switch ($type) {
                    case 'VALUE':
                    case 'VALUE_OR_KEY':
                        $type = 'NEW';
                        break;
                    default:
                        throw new ForkException('Config array cannot be parsed (6)');
                }
            // присвоение значения
            } elseif ('=>' === $token) {
                switch ($type) {
                    case 'VALUE_OR_KEY':
                        $key          = $value;
                        $key_before   = $value_before;
                        $key_after    = $other;
                        $other        = '';
                        $value        = null;
                        $value_before = '';
                        $type         = '=>';
                        break;
                    default:
                        throw new ForkException('Config array cannot be parsed (7)');
                }

            // пробел, комментарий
            } elseif (
                '' === \trim($token)
                || 0 === \strpos($token, '//')
                || 0 === \strpos($token, '/*')
                || '#' === $token[0]
            ) {
                switch ($type) {
                    case 'NEW':
                    case 'VALUE_OR_KEY':
                    case 'VALUE':
                    case '=>':
                            $other .= $token;
                        break;
                    default:
                        throw new ForkException('Config array cannot be parsed (8)');
                }
            // какое-то значение
            } else {
                switch ($type) {
                    case 'NEW':
                        if (null !== $value) {
                            \preg_match('%^([^\r\n]*+)(.*)$%s', $other, $matches);
                            $value_after = $matches[1];
                            $other       = $matches[2];

                            $value = [
                                'value'        => $value,
                                'value_before' => $value_before,
                                'value_after'  => $value_after,
                                'key_before'   => $key_before,
                                'key_after'    => $key_after,
                            ];

                            $value_before = '';
                            $value_after  = '';
                            $key_before   = '';
                            $key_after    = '';

                            if (null !== $key) {
                                $result[$this->clearKey($key)] = $value;
                            } else {
                                $result[] = $value;
                            }

                            $value = null;
                            $key   = null;
                        } elseif (null !== $key) {
                            throw new ForkException('Config array cannot be parsed (9)');
                        }

                        $type = 'VALUE_OR_KEY';
                        break;
                    case '=>':
                        $type = 'VALUE';
                        break;
                    default:
                        throw new ForkException('Config array cannot be parsed (10)');
                }

                $value        = $token;
                $value_before = $other;
                $other        = '';
            }

            ++$this->position;
        }
    }

    protected function isFormat(mixed $data): bool
    {
        return \is_array($data)
        && \array_key_exists('value', $data)
        && \array_key_exists('value_before', $data)
        && \array_key_exists('value_after', $data)
        && \array_key_exists('key_before', $data)
        && \array_key_exists('key_after', $data);
    }

    /**
     * Добавляет/заменяет элемент в конфиг(е)
     */
    public function add(string $path, mixed $value, string $after = null): bool
    {
        if (empty($this->configArray)) {
            $this->configArray = $this->getArray();
        }

        $pathArray = \explode('=>', $path);
        $size      = \count($pathArray);
        $i         = 0;
        $config    = &$this->configArray;

        while ($i < $size - 1) {
            $key = $pathArray[$i];

            if (\is_numeric($key)) { //???? O_o
                $config[] = [];
                $config   = &$config[\array_key_last($config)];
            } else {
                $config[$key] ??= [];

                if ($this->isFormat($config[$key])) {
                    $config = &$config[$key]['value'];
                } else {
                    $config = &$config[$key];
                }
            }

            ++$i;
        }

        $key = $pathArray[$i];

        if (
            \is_numeric($key) //???? O_o
            || \is_numeric($after) //???? O_o O_o O_o
        ) {
            $config[] = $value;
        } elseif (isset($config[$key])) {
            if (
                $this->isFormat($config[$key])
                && ! $this->isFormat($value)
            ) {
                $config[$key]['value'] = $value;
            } else {
                $config[$key] = $value;
            }
        } elseif (
            null === $after
            || ! isset($config[$after])
        ) {
            $config[$key] = $value;
        } else {
            $new = [];

            foreach ($config as $k => $v) {
                if (\is_int($k)) {
                    $new[] = $v;
                } else {
                    $new[$k] = $v;

                    if ($k === $after) {
                        $new[$key] = $value;
                    }
                }
            }

            $config = $new;
        }

        return true;
    }

    /**
     * Удаляет элемент из конфига
     */
    public function delete(string $path): mixed
    {
        if (empty($this->configArray)) {
            $this->configArray = $this->getArray();
        }

        $pathArray = \explode('=>', $path);
        $size      = \count($pathArray);
        $i         = 0;
        $config    = &$this->configArray;

        while ($i < $size - 1) {
            $key = $pathArray[$i];

            if (! \array_key_exists($key, $config)) {
                return false;
            }

            if ($this->isFormat($config[$key])) {
                $config = &$config[$key]['value'];
            } else {
                $config = &$config[$key];
            }

            ++$i;
        }

        $key = $pathArray[$i];

        if (! \array_key_exists($key, $config)) {
            return false;
        } else {
            $result = $config[$key];
            unset($config[$key]);

            return $result;
        }
    }

    /**
     * Записывает файл конфига с перестройкой массива
     */
    public function save(): void
    {
        $contents = \str_replace(
            $this->configStr,
            $this->toStr($this->configArray, 1),
            $this->fileContents,
            $count
        );

        if (1 !== $count) {
            throw new ForkException('Config array cannot be replace');
        }

        if (false === \file_put_contents($this->path, $contents, \LOCK_EX)) {
            throw new ForkException('Config can not be write');
        }
    }

    /**
     * Преобразует массив в строку
     */
    protected function toStr(array $data, int $level): string
    {
        $space  = \str_repeat('    ', $level);
        $result = '[';
        $tail   = '';

        foreach ($data as $key => $cur) {
            $tail = '';

            if ($this->isFormat($cur)) {
                if (\is_string($key)) {
                    $result .= "{$cur['key_before']}'{$key}'{$cur['key_after']}=>{$cur['value_before']}";
                } else {
                    $result .= "{$cur['value_before']}";
                }

                if (\is_array($cur['value'])) {
                    $result .= $this->toStr($cur['value'], $level + 1) . ",{$cur['value_after']}";
                } else {
                    $result .= "{$cur['value']},{$cur['value_after']}";
                }
            } else {
                if (\is_string($key)) {
                    $result  = \rtrim($result, "\n\t ");
                    $result .= "\n{$space}'{$key}' => ";
                    $tail    = "\n" . \str_repeat('    ', $level - 1);
                } else {
                    $result .= ' ';
                }

                if (\is_array($cur)) {
                    $result .= $this->toStr($cur, $level + 1) . ',';
                } else {
                    $result .= "{$cur},";
                }
            }
        }

        return \rtrim($result . $tail, ',')  . ']';
    }
}
