<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\DB\DBStatement;
use PDO;
use PDOStatement;
use PDOException;
use SensitiveParameter;

class DB
{
    /**
     * Экземпляр PDO через который идет работа с бд
     */
    protected ?PDO $pdo;

    /**
     * Префикс для таблиц базы
     */
    protected string $dbPrefix;

    /**
     * Тип базы данных
     */
    protected string $dbType;

    /**
     * Имя класса для драйвера
     */
    protected string $dbDrvClass;

    /**
     * Драйвер текущей базы
     * @var //????
     */
    protected $dbDrv;

    /**
     * Имя класса для PDOStatement
     */
    protected string $statementClass;

    /**
     * Количество выполненных запросов
     */
    protected int $qCount = 0;

    /**
     * Выполненные запросы
     */
    protected array $queries = [];

    /**
     * Дельта времени для следующего запроса
     */
    protected float $delta = 0;

    protected array $pdoMethods = [
        'beginTransaction'      => true,
        'commit'                => true,
        'errorCode'             => true,
        'errorInfo'             => true,
        'exec'                  => true,
        'getAttribute'          => true,
        'getAvailableDrivers'   => true,
        'inTransaction'         => true,
        'lastInsertId'          => true,
        'prepare'               => true,
        'query'                 => true,
        'quote'                 => true,
        'rollBack'              => true,
        'setAttribute'          => true,

        'pgsqlCopyFromArray'    => true,
        'pgsqlCopyFromFile'     => true,
        'pgsqlCopyToArray'      => true,
        'pgsqlCopyToFile'       => true,
        'pgsqlGetNotify'        => true,
        'pgsqlGetPid'           => true,
        'pgsqlLOBCreate'        => true,
        'pgsqlLOBOpen'          => true,
        'pgsqlLOBUnlink'        => true,

        'sqliteCreateAggregate' => true,
        'sqliteCreateCollation' => true,
        'sqliteCreateFunction'  => true,
    ];

    public function __construct(
        string $dsn,
        string $username = null,
        #[SensitiveParameter] string $password = null,
        array $options = [],
        string $prefix = ''
    ) {
        $dsn = $this->initialConfig($dsn);

        if (\preg_match('%[^\w]%', $prefix)) {
            throw new PDOException("Bad prefix");
        }

        $this->dbPrefix = $prefix;

        list($initSQLCommands, $initFunction) = $this->prepareOptions($options);

        $start     = \microtime(true);
        $this->pdo = new PDO($dsn, $username, $password, $options);

        $this->saveQuery('PDO::__construct()', \microtime(true) - $start, false);

        if (\is_string($initSQLCommands)) {
            $this->exec($initSQLCommands);
        }

        if (
            null !== $initFunction
            && true !== $initFunction($this)
        ) {
            throw new PDOException("initFunction failure");
        }

        $this->beginTransaction();
    }

    protected function initialConfig(string $dsn): string
    {
        $type = \strstr($dsn, ':', true);

        if (! \in_array($type, PDO::getAvailableDrivers(), true)) {
            throw new PDOException("PDO does not have driver for '{$type}'");
        }

        $typeU = \ucfirst($type);

        if (! \is_file(__DIR__ . "/DB/{$typeU}.php")) {
            throw new PDOException("Driver isn't found for '$type'");
        }

        $this->dbType     = $type;
        $this->dbDrvClass = "ForkBB\\Core\\DB\\{$typeU}";

        if (\is_file(__DIR__ . "/DB/{$typeU}Statement.php")) {
            $this->statementClass = "ForkBB\\Core\\DB\\{$typeU}Statement";
        } else {
            $this->statementClass = DBStatement::class;
        }

        if ('sqlite' === $type) {
            $dsn = \str_replace('!PATH!', \realpath(__DIR__ . '/../config/db') . '/', $dsn);
        }

        return $dsn;
    }

    protected function prepareOptions(array &$options): array
    {
        $result = [
            0 => null,
            1 => null,
        ];

        if (isset($options['initSQLCommands'])) {
            $result[0] = \implode(';', $options['initSQLCommands']);

            unset($options['initSQLCommands']);
        }

        if (isset($options['initFunction'])) {
            $result[1] = $options['initFunction'];

            unset($options['initFunction']);
        }

        $options += [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        ];

        return $result;
    }

    protected function dbStatement(PDOStatement $stmt): DBStatement
    {
        return new $this->statementClass($this, $stmt);
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

                $map[$key] ??= [$type];
                $res         = [];

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
    public function getValue(int|string $key, array $params): mixed
    {
        if (
            \is_string($key)
            && \array_key_exists(':' . $key, $params)
        ) {
            return $params[':' . $key];
        } elseif (\array_key_exists($key, $params)) {
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
    public function exec(string $query, array $params = []): int|false
    {
        $map = $this->parse($query, $params);

        if (empty($params)) {
            $start  = \microtime(true);
            $result = $this->pdo->exec($query);

            $this->saveQuery($query, \microtime(true) - $start);

            return $result;
        }

        $start       = \microtime(true);
        $stmt        = $this->pdo->prepare($query);
        $this->delta = \microtime(true) - $start;

        if (! $stmt instanceof PDOStatement) {
            return false;
        }

        $stmt = $this->dbStatement($stmt);

        $stmt->setMap($map);

        if ($stmt->execute($params)) {
            return $stmt->rowCount(); //??? Для запроса SELECT... не ясно поведение!
        }

        return false;
    }

    /**
     * Метод расширяет PDO::prepare()
     */
    public function prepare(string $query, array $params = [], array $options = []): DBStatement|false
    {
        $map         = $this->parse($query, $params);
        $start       = \microtime(true);
        $stmt        = $this->pdo->prepare($query, $options);
        $this->delta = \microtime(true) - $start;

        if (! $stmt instanceof PDOStatement) {
            return false;
        }

        $stmt = $this->dbStatement($stmt);

        $stmt->setMap($map);
        $stmt->bindValueList($params);

        return $stmt;
    }

    /**
     * Метод расширяет PDO::query()
     */
    public function query(string $query, mixed ...$args): DBStatement|false
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
            $start = \microtime(true);
            $stmt  = $this->pdo->query($query, ...$args);

            $this->saveQuery($query, \microtime(true) - $start);

            if (! $stmt instanceof PDOStatement) {
                return false;
            }

            return $this->dbStatement($stmt);
        }

        $start       = \microtime(true);
        $stmt        = $this->pdo->prepare($query);
        $this->delta = \microtime(true) - $start;

        if (! $stmt instanceof PDOStatement) {
            return false;
        }

        $stmt = $this->dbStatement($stmt);

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
        $result = $this->pdo->beginTransaction();

        $this->saveQuery('beginTransaction()', \microtime(true) - $start, false);

        return $result;
    }

    /**
     * Фиксирует транзакцию
     */
    public function commit(): bool
    {
        $start  = \microtime(true);
        $result = $this->pdo->commit();

        $this->saveQuery('commit()', \microtime(true) - $start, false);

        return $result;
    }

    /**
     * Откатывает транзакцию
     */
    public function rollback(): bool
    {
        $start  = \microtime(true);
        $result = $this->pdo->rollback();

        $this->saveQuery('rollback()', \microtime(true) - $start, false);

        return $result;
    }

    /**
     * Передает вызовы метода в PDO или драйвер текущей базы
     */
    public function __call(string $name, array $args): mixed
    {
        if (isset($this->pdoMethods[$name])) {
            return $this->pdo->$name(...$args);
        } elseif (empty($this->dbDrv)) {
            $this->dbDrv = new $this->dbDrvClass($this, $this->dbPrefix);

            // ????? проверка типа
        }

        return $this->dbDrv->$name(...$args);
    }

    /**
     * Уничтожает (или пытается?) объект PDO
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }
}
