<?php

namespace ForkBB\Core;

use ForkBB\Core\Exceptions\ForkException;
use InvalidArgumentException;

class Config
{
    /**
     * Путь до файла конфига
     * @var string
     */
    protected $path;

    /**
     * Содержимое файла конфига
     * @var string
     */
    protected $configFile;

    /**
     * Начальная позиция массива конфига
     * @var int
     */
    protected $configArrPos;

    /**
     * Отступ элементов конфига первого уровня от начала строки
     * @var string
     */
    protected $configWhitespace;

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

        $this->configFile = \file_get_contents($path);

        if (\preg_match('%\[[\n\r]+(\ +)\'BASE_URL\'\s+=>%', $this->configFile, $matches, \PREG_OFFSET_CAPTURE)) {
            $this->configArrPos     = $matches[0][1];
            $this->configWhitespace = $matches[1][0];

            return;
        }

        throw new ForkException('The structure of the config file is undefined');
    }

    /**
     * Добавляет/заменяет данные в конфиг(е)
     */
    public function add(array $data, string $position = null): bool
    {

    }

    protected $tokens;
    protected $position;

    protected function getArray(): array
    {
        if (
            false === \preg_match_all(
                '%//[^\n\r]*+|\'.*?(?<!\\\\)\'|".*?(?<!\\\\)"|\s+|\[|\]|,|=>|\S+(?<![,\]\)])%s',
                \substr($this->configFile, $this->configArrPos),
                $matches
            )
            || empty($matches)
        ) {
            throw new ForkException('Config array cannot be parsed');
        }

        $this->tokens   = $matches[0];
        $this->position = 0;

        return $this->parse('ZERO');
    }

    protected function parse($type): array
    {
        $result = [];
        $value  = null;
        $key    = null;

        while (isset($this->tokens[$this->position])) {
            $token = $this->tokens[$this->position];

            // открытие массива
            if ('[' === $token) {
                switch ($type) {
                    case 'ZERO':
                        $type = 'NEW';
                        break;
                    case 'NEW':
                    case '=>':
                        $value = $this->parse('ZERO');
                        $type  = 'VALUE';
                        break;
                    default:
                        exit('error' . $this->position);
                }
            // закрытие массива
            } elseif (']' === $token) {
                switch ($type) {
                    case 'NEW':
                        break;
                    case 'VALUE':
                        if (null !== $key) {
                            $result[$key] = $value;
                            break;
                        }
                    case 'VALUE_OR_KEY':
                        $result[] = $value;
                        break;
                    default:
                        exit('error' . $this->position);
                }

                return $result;
            // новый элемент
            } elseif (',' === $token) {
                switch ($type) {
                    case 'VALUE':
                        if (null !== $key) {
                            $result[$key] = $value;
                            break;
                        }
                    case 'VALUE_OR_KEY':
                        $result[] = $value;
                        break;
                    default:
                        exit('error' . $this->position);
                }

                $type  = 'NEW';
                $value = null;
                $key   = null;
            // присвоение значения
            } elseif ('=>' === $token) {
                switch ($type) {
                    case 'VALUE_OR_KEY':
                        $key   = $value;
                        $value = null;
                        $type = '=>';
                        break;
                    default:
                        exit('error' . $this->position);
                }
            // комментарий
            } elseif (0 === \strpos($token, '//')) {

            // пробел
            } elseif ('' === \trim($token)) {
                switch ($type) {
                    case 'NEW':
                    case 'VALUE_OR_KEY':
                    case 'VALUE':
                    case '=>':
                            $lastSpace = $token;
                        break;
                    default:
                        exit('error' . $this->position);
                }
            // какое-то значение
            } else {
                switch ($type) {
                    case 'NEW':
                        $type = 'VALUE_OR_KEY';
                        break;
                    case '=>':
                        $type = 'VALUE';
                        break;
                    default:
                        exit('error' . $this->position);
                }

                $value = $token;
            }

            ++$this->position;
        }
    }
}
