<?php

namespace ForkBB\Core;

use ForkBB\Core\DBStatement;
use PDO;
use PDOStatement;
use PDOException;

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

    /**
     * Конструктор
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     * @param string $prefix
     *
     * @throws PDOException
     */
    public function __construct($dsn, $username = null, $password = null, array $options = [], $prefix = '')
    {
        $type = \strstr($dsn, ':', true);
        if (! $type || ! \is_file(__DIR__ . '/DB/' . \ucfirst($type) . '.php')) {
            throw new PDOException("Driver isn't found for '$type'");
        }
        $this->dbType = $type;

        $this->dbPrefix = $prefix;
        $options += [
            self::ATTR_DEFAULT_FETCH_MODE => self::FETCH_ASSOC,
            self::ATTR_EMULATE_PREPARES   => false,
            self::ATTR_ERRMODE            => self::ERRMODE_EXCEPTION,
            self::ATTR_STATEMENT_CLASS    => [DBStatement::class, [$this]],
        ];

        parent::__construct($dsn, $username, $password, $options);
    }

    /**
     * Передает вызовы методов в драйвер текущей базы
     *
     * @param string $name
     * @param array $args
     *
     * @return mixed
     */
    public function __call($name, array $args)
    {
        if (empty($this->dbDrv)) {
            $drv = 'ForkBB\\Core\\DB\\' . \ucfirst($this->dbType);
            $this->dbDrv = new $drv($this, $this->dbPrefix);
        }
        return $this->dbDrv->$name(...$args);
    }

    /**
     * Метод определяет массив ли опций подан на вход
     *
     * @param array $options
     *
     * @return bool
     */
    protected function isOptions(array $arr)
    {
        $verify = [self::ATTR_CURSOR => [self::CURSOR_FWDONLY, self::CURSOR_SCROLL]];

        foreach ($arr as $key => $value) {
           if (! isset($verify[$key]) || ! \in_array($value, $verify[$key])) {
               return false;
           }
        }
        return true;
    }

    /**
     * Метод приводит запрос с типизированными плейсхолдерами к понятному для PDO виду
     *
     * @param string &$query
     * @param array $params
     *
     * @throws PDOException
     *
     * @return array
     */
    protected function parse(&$query, array $params)
    {
        $idxIn = 0;
        $idxOut = 1;
        $map = [];
        $query = \preg_replace_callback(
            '%(?=[?:])(?<![\w?:])(?:::(\w+)|\?(?![?:])(?:(\w+)(?::(\w+))?)?|:(\w+))%',
            function($matches) use ($params, &$idxIn, &$idxOut, &$map) {
                if (! empty($matches[1])) {
                    return $this->dbPrefix . $matches[1];
                }

                $type = empty($matches[2]) ? 's' : $matches[2];
                $key = isset($matches[4]) ? $matches[4] : (isset($matches[3]) ? $matches[3] : $idxIn++);
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
                        $value = [1];
                        break;
                    default:
                        $value = [1];
                        $type = 's';
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
                    $name = ':' . $idxOut++;
                    $res[] = $name;
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
     *
     * @param mixed $key
     * @param array $params
     *
     * @throws PDOException
     *
     * @return mixed
     */
    public function getValue($key, array $params)
    {
        if (
            \is_string($key)
            && (isset($params[':' . $key]) || \array_key_exists(':' . $key, $params))
        ) {
            return $params[':' . $key];
        } elseif (
            (\is_string($key) || \is_int($key))
            && (isset($params[$key]) || \array_key_exists($key, $params))
        ) {
            return $params[$key];
        }

        throw new PDOException("The '{$key}' key is not found in the parameters");
    }

    /**
     * Метод для получения количества выполненных запросов
     *
     * @return int
     */
    public function getCount()
    {
        return $this->qCount;
    }

    /**
     * Метод для получения статистики выполненных запросов
     *
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * Метод для сохранения статистики по выполненному запросу
     *
     * @param string $query
     * @param float $time
     */
    public function saveQuery($query, $time)
    {
        $this->qCount++;
        $this->queries[] = [$query, $time + $this->delta];
        $this->delta = 0;
    }

    /**
     * Метод расширяет PDO::exec()
     *
     * @param string $query
     * @param array $params
     *
     * @return int|false
     */
    public function exec($query, array $params = [])
    {
        $map = $this->parse($query, $params);

        if (empty($params)) {
            $start  = \microtime(true);
            $result = parent::exec($query);
            $this->saveQuery($query, \microtime(true) - $start);
            return $result;
        }

        $start = \microtime(true);
        $stmt  = parent::prepare($query);
        $this->delta = \microtime(true) - $start;

        $stmt->setMap($map);

        if ($stmt->execute($params)) {
            return $stmt->rowCount(); //??? Для запроса SELECT... не ясно поведение!
        }

        return false;
    }

    /**
     * Метод расширяет PDO::prepare()
     *
     * @param string $query
     * @param array $arg1
     * @param array $arg2
     *
     * @return PDOStatement
     */
    public function prepare($query, $arg1 = null, $arg2 = null)
    {
        if (empty($arg1) === empty($arg2) || ! empty($arg2)) {
            $params = $arg1;
            $options = $arg2;
        } elseif ($this->isOptions($arg1)) {
            $params = [];
            $options = $arg1;
        } else {
            $params = $arg1;
            $options = [];
        }

        $map = $this->parse($query, $params);

        $start = \microtime(true);
        $stmt  = parent::prepare($query, $options);
        $this->delta = \microtime(true) - $start;

        $stmt->setMap($map);

        $stmt->bindValueList($params);

        return $stmt;
    }

    /**
     * Метод расширяет PDO::query()
     *
     * @param string $query
     * @param mixed ...$args
     *
     * @return PDOStatement|false
     */
    public function query($query, ...$args)
    {
        if (isset($args[0]) && \is_array($args[0])) {
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

        $start = \microtime(true);
        $stmt  = parent::prepare($query);
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
}
