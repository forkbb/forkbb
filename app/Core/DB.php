<?php

namespace ForkBB\Core;

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
        $type = strstr($dsn, ':', true);
        if (! $type || ! file_exists(__DIR__ . '/DB/' . ucfirst($type) . '.php')) {
            throw new PDOException("For '$type' the driver isn't found.");
        }
        $this->dbType = $type;

        $this->dbPrefix = $prefix;
        $options += [
            self::ATTR_DEFAULT_FETCH_MODE => self::FETCH_ASSOC,
            self::ATTR_EMULATE_PREPARES   => false,
            self::ATTR_ERRMODE            => self::ERRMODE_EXCEPTION,
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
            $drv = 'ForkBB\\Core\\DB\\' . ucfirst($this->dbType);
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
           if (! isset($verify[$key]) || ! in_array($value, $verify[$key])) {
               return false;
           }
        }
        return true;
    }

    /**
     * Метод приводит запрос с типизированными плейсхолдерами к понятному для PDO виду
     *
     * @param string $query
     * @param array $params
     *
     * @throws PDOException
     *
     * @return array
     */
    protected function parse($query, array $params)
    {
        $parts = preg_split('%(?=[?:])(?<![a-z0-9_])(\?[a-z0-9_]+|\?(?!=:))?(::?[a-z0-9_]+)?%i', $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $idxIn = 0;
        $idxOut = 1;
        $query = '';
        $total = count($parts);
        $map = [];
        $bind = [];

        for ($i = 0; $i < $total; ++$i) {
            switch ($parts[$i][0]) {
                case '?':
                    $type = isset($parts[$i][1]) ? substr($parts[$i], 1) : 's';
                    $key = isset($parts[$i + 1]) && $parts[$i + 1][0] === ':'
                           ? $parts[++$i]
                           : $idxIn++;
                    break;
                case ':':
                    if ($parts[$i][1] === ':') {
                        $query .= $this->dbPrefix . substr($parts[$i], 2);
                        continue 2;
                    }
                    $type = 's';
                    $key = $parts[$i];
                    break;
                default:
                    $query .= $parts[$i];
                    continue 2;
            }

            if (! isset($params[$key])) {
                throw new PDOException("'$key': No parameter for (?$type) placeholder");
            }

            switch ($type) {
                case 'p':
                    $query .= (string) $params[$key];
                    continue 2;
                case 'as':
                case 'ai':
                case 'a':
                    $bindType = $type === 'ai' ? self::PARAM_INT : self::PARAM_STR;
                    $comma = '';
                    foreach ($params[$key] as $val) {
                        $name = ':' . $idxOut++;
                        $query .= $comma . $name;
                        $bind[$name] = [$val, $bindType];
                        $comma = ',';

                        if (empty($map[$key])) {
                            $map[$key] = [$type, $name];
                        } else {
                            $map[$key][] = $name;
                        }
                    }
                    continue 2;
                case '':
                    break;
                case 'i':
                    $bindType = self::PARAM_INT;
                    break;
                case 'b':
                    $bindType = self::PARAM_BOOL;
                    break;
                case 's':
                default:
                    $bindType = self::PARAM_STR;
                    $type = 's';
                    break;
            }

            $name = ':' . $idxOut++;
            $query .= $name;
            $bind[$name] = [$params[$key], $bindType];

            if (empty($map[$key])) {
                $map[$key] = [$type, $name];
            } else {
                $map[$key][] = $name;
            }
        }

        return [$query, $bind, $map];
    }

    /**
     * Метод связывает параметры запроса с соответвтующими значениями
     *
     * @param PDOStatement $stmt
     * @param array $bind
     */
    protected function bind(PDOStatement $stmt, array $bind)
    {
        foreach ($bind as $key => $val) {
            $stmt->bindValue($key, $val[0], $val[1]);
        }
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
        list($query, $bind, ) = $this->parse($query, $params);

        if (empty($bind)) {
            return parent::exec($query);
        }

        $stmt = parent::prepare($query);
        $this->bind($stmt, $bind);

        if ($stmt->execute()) {
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

        list($query, $bind, $map) = $this->parse($query, $params);
        $stmt = parent::prepare($query, $options);
        $this->bind($stmt, $bind);

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
        if (isset($args[0]) && is_array($args[0])) {
            $params = array_shift($args);
        } else {
            $params = [];
        }

        list($query, $bind, ) = $this->parse($query, $params);

        if (empty($bind)) {
            return parent::query($query, ...$args);
        }

        $stmt = parent::prepare($query);
        $this->bind($stmt, $bind);

        if ($stmt->execute()) {
            if (! empty($args)) {
                $stmt->setFetchMode(...$args);
            }

            return $stmt;
        }

        return false;
    }
}
