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
            throw new PDOException("Driver isn't found for '$type'");
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
        $idxIn = 0;
        $idxOut = 1;
        $map = [];
        $bind = [];
        $query = preg_replace_callback(
            '%(?=[?:])(?<![\w?])(\?(?![:?])(\w+)?)?(?:(::?)(\w+))?%i', 
            function($matches) use ($params, &$idxIn, &$idxOut, &$map, &$bind) {
                if (isset($matches[3]) && $matches[3] === '::') {
                    return $this->dbPrefix . $matches[4];
                }
                
                $type = $matches[2] ?: 's';

                if (isset($matches[4])) {
                    $key1 = ':' . $matches[4];
                    $key2 = $matches[4];
                } else {
                    $key1 = $idxIn;
                    $key2 = $idxIn;
                    ++$idxIn;
                }

                if (isset($params[$key1]) || array_key_exists($key1, $params)) {
                    $value = $params[$key1];
                } elseif (isset($params[$key2]) || array_key_exists($key2, $params)) {
                    $value = $params[$key2];
                } else {
                    throw new PDOException("'$key1': No parameter for (?$type) placeholder");
                }

                switch ($type) {
                    case 'p':
                        return (string) $value;
                    case 'ai':
                        $bindType = self::PARAM_INT;
                        break;
                    case 'as':
                    case 'a':
                        $bindType = self::PARAM_STR;
                        break;
                    case 'i':
                        $bindType = self::PARAM_INT;
                        $value = [$value];
                        break;
                    case 'b':
                        $bindType = self::PARAM_BOOL;
                        $value = [$value];
                        break;
                    case 's':
                    default:
                        $bindType = self::PARAM_STR;
                        $value = [$value];
                        $type = 's';
                        break;
                }

                $res = [];
                foreach ($value as $val) {
                    $name = ':' . $idxOut;
                    ++$idxOut;
                    $res[] = $name;
                    $bind[$name] = [$val, $bindType];

                    if (empty($map[$key2])) {
                        $map[$key2] = [$type, $name];
                    } else {
                        $map[$key2][] = $name;
                    }
                }
                return implode(',', $res);
            }, 
            $query
        );
//var_dump($query);
//var_dump($bind);
//var_dump($map);
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
