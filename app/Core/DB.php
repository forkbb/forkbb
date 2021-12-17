<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\DBStatement;
use PDO;
use PDOStatement;
use PDOException;
use ReturnTypeWillChange;

class DB extends PDO
{
    /**
     * Префикс для таблиц базы
     * @var string
     */
    protected $dbPrefix;

    /**
     * Тип базы данных
     * @var string
     */
    protected $dbType;

    /**
     * Драйвер текущей базы
     * @var //????
     */
    protected $dbDrv;

    /**
     * Количество выполненных запросов
     * @var int
     */
    protected $qCount = 0;

    /**
     * Выполненные запросы
     * @var array
     */
    protected $queries = [];

    /**
     * Дельта времени для следующего запроса
     * @var float
     */
    protected $delta = 0;

    public function __construct(string $dsn, string $username = null, string $password = null, array $options = [], string $prefix = '')
    {
        $type  = \strstr($dsn, ':', true);
        $typeU = \ucfirst($type);

        if (
            ! $type
            || ! \in_array($type, PDO::getAvailableDrivers(), true)
            || ! \is_file(__DIR__ . "/DB/{$typeU}.php")
        ) {
            throw new PDOException("Driver isn't found for '$type'");
        }

        $statement = $typeU . 'Statement' . (\PHP_MAJOR_VERSION < 8 ? '7' : '');

        if (\is_file(__DIR__ . "/DB/{$statement}.php")) {
            $statement = 'ForkBB\\Core\\DB\\' . $statement;
        } else {
            $statement = DBStatement::class;
        }

        if ('sqlite' === $type) {
            $dsn = \str_replace('!PATH!', \realpath(__DIR__ . '/../config/db') . '/', $dsn);
        }

        $this->dbType   = $type;
        $this->dbPrefix = $prefix;

        if (isset($options['initSQLCommands'])) {
            $initSQLCommands = implode(';', $options['initSQLCommands']);

            unset($options['initSQLCommands']);
        } else {
            $initSQLCommands = null;
        }

        $options += [
            self::ATTR_DEFAULT_FETCH_MODE => self::FETCH_ASSOC,
            self::ATTR_EMULATE_PREPARES   => false,
            self::ATTR_STRINGIFY_FETCHES  => false,
            self::ATTR_ERRMODE            => self::ERRMODE_EXCEPTION,
            self::ATTR_STATEMENT_CLASS    => [$statement, [$this]],
        ];

        $start  = \microtime(true);

        parent::__construct($dsn, $username, $password, $options);

        $this->saveQuery('PDO::__construct()', \microtime(true) - $start, false);

        if ($initSQLCommands) {
            $this->exec($initSQLCommands);
        }

        $this->beginTransaction();
    }

    /**
     * Передает вызовы методов в драйвер текущей базы
     */
    public function __call(string $name, array $args) /* : mixed */
    {
        if (empty($this->dbDrv)) {
            $drv = 'ForkBB\\Core\\DB\\' . \ucfirst($this->dbType);
            $this->dbDrv = new $drv($this, $this->dbPrefix);
        }

        return $this->dbDrv->$name(...$args);
    }

    /**
     * Метод определяет массив ли опций подан на вход
     */
    protected function isOptions(array $options): bool
    {
        $verify = [self::ATTR_CURSOR => [self::CURSOR_FWDONLY, self::CURSOR_SCROLL]];

        foreach ($options as $key => $value) {
           if (
               ! isset($verify[$key])
               || ! \in_array($value, $verify[$key], true)
            ) {
               return false;
           }
        }

        return true;
    }

    /**
     * Метод приводит запрос с типизированными плейсхолдерами к понятному для PDO виду
     */
    protected function parse(string &$query, array $params): array
    {
        $idxIn  = 0;
        $idxOut = 1;
        $map    = [];
        $query  = \preg_replace_callback(
            '%(?=[?:])(?<![\w?:])(?:::(\w+)|\?(?![?:])(?:(\w+)(?::(\w+))?)?|:(\w+))%',
            function ($matches) use ($params, &$idxIn, &$idxOut, &$map) {
                if (! empty($matches[1])) {
                    return $this->dbPrefix . $matches[1];
                }

                $type  = empty($matches[2]) ? 's' : $matches[2];
                $key   = $matches[4] ?? ($matches[3] ?? $idxIn++);
                $value = $this->getValue($key, $params);

                switch ($type) {
                    case 'p':
                        return (string) $value;
                    case 'ai':
                    case 'as':
                    case 'a':
                        break;
                    case 'i':
                    case 'b':
                    case 's':
                    case 'f':
                        $value = [1];
                        break;
                    default:
                        $value = [1];
                        $type  = 's';
                        break;
                }

                if (! \is_array($value)) {
                    throw new PDOException("Expected array: key='{$key}', type='{$type}'");
                }

                if (! isset($map[$key])) {
                    $map[$key] = [$type];
                }

                $res = [];
                foreach ($value as $val) {
                    $name        = ':' . $idxOut++;
                    $res[]       = $name;
                    $map[$key][] = $name;
                }

                return \implode(',', $res);
            },
            $query
        );

        return $map;
    }

    /**
     * Метод возвращает значение из массива параметров по ключу или исключение
     */
    public function getValue(/* mixed */ $key, array $params) /* : mixed */
    {
        if (
            \is_string($key)
            && \array_key_exists(':' . $key, $params)
        ) {
            return $params[':' . $key];
        } elseif (
            (
                \is_string($key)
                || \is_int($key)
            )
            && \array_key_exists($key, $params)
        ) {
            return $params[$key];
        }

        throw new PDOException("The '{$key}' key is not found in the parameters");
    }

    /**
     * Метод для получения количества выполненных запросов
     */
    public function getCount(): int
    {
        return $this->qCount;
    }

    /**
     * Метод для получения статистики выполненных запросов
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Возвращает тип базы данных указанный в DSN
     */
    public function getType(): string
    {
        return $this->dbType;
    }

    /**
     * Метод для сохранения статистики по выполненному запросу
     */
    public function saveQuery(string $query, float $time, bool $add = true): void
    {
        if ($add) {
            ++$this->qCount;
        }
        $this->queries[] = [$query, $time + $this->delta];
        $this->delta     = 0;
    }

    /**
     * Метод расширяет PDO::exec()
     */
    #[ReturnTypeWillChange]
    public function exec(/* string */ $query, array $params = []) /* : int|false */
    {
        $map = $this->parse($query, $params);

        if (empty($params)) {
            $start  = \microtime(true);
            $result = parent::exec($query);
            $this->saveQuery($query, \microtime(true) - $start);

            return $result;
        }

        $start       = \microtime(true);
        $stmt        = parent::prepare($query);
        $this->delta = \microtime(true) - $start;

        $stmt->setMap($map);

        if ($stmt->execute($params)) {
            return $stmt->rowCount(); //??? Для запроса SELECT... не ясно поведение!
        }

        return false;
    }

    /**
     * Метод расширяет PDO::prepare()
     */
    #[ReturnTypeWillChange]
    public function prepare(/* string */ $query, /* array */ $arg1 = null, /* array */ $arg2 = null): PDOStatement
    {
        if (
            empty($arg1) === empty($arg2)
            || ! empty($arg2)
        ) {
            $params  = $arg1;
            $options = $arg2;
        } elseif ($this->isOptions($arg1)) {
            $params  = [];
            $options = $arg1;
        } else {
            $params  = $arg1;
            $options = [];
        }

        $map = $this->parse($query, $params);

        $start       = \microtime(true);
        $stmt        = parent::prepare($query, $options);
        $this->delta = \microtime(true) - $start;

        $stmt->setMap($map);

        $stmt->bindValueList($params);

        return $stmt;
    }

    /**
     * Метод расширяет PDO::query()
     */
    #[ReturnTypeWillChange]
    public function query(string $query, /* mixed */ ...$args) /* : PDOStatement|false */
    {
        if (
            isset($args[0])
            && \is_array($args[0])
        ) {
            $params = \array_shift($args);
        } else {
            $params = [];
        }

        $map = $this->parse($query, $params);

        if (empty($params)) {
            $start  = \microtime(true);
            $result = parent::query($query, ...$args);
            $this->saveQuery($query, \microtime(true) - $start);

            return $result;
        }

        $start       = \microtime(true);
        $stmt        = parent::prepare($query);
        $this->delta = \microtime(true) - $start;

        $stmt->setMap($map);

        if ($stmt->execute($params)) {
            if (! empty($args)) {
                $stmt->setFetchMode(...$args);
            }

            return $stmt;
        }

        return false;
    }

    /**
     * Инициализирует транзакцию
     */
    public function beginTransaction(): bool
    {
        $start  = \microtime(true);
        $result = parent::beginTransaction();
        $this->saveQuery('beginTransaction()', \microtime(true) - $start, false);

        return $result;
    }

    /**
     * Фиксирует транзакцию
     */
    public function commit(): bool
    {
        $start  = \microtime(true);
        $result = parent::commit();
        $this->saveQuery('commit()', \microtime(true) - $start, false);

        return $result;
    }

    /**
     * Откатывает транзакцию
     */
    public function rollback(): bool
    {
        $start  = \microtime(true);
        $result = parent::rollback();
        $this->saveQuery('rollback()', \microtime(true) - $start, false);

        return $result;
    }
}
