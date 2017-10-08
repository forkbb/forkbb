<?php

namespace ForkBB\Core;

use PDO;
use PDOStatement;
use PDOException;

class DBStatement extends PDOStatement
{
    /**
     * Префикс для таблиц базы
     * @var PDO
     */
    protected $db;

    /**
     * Карта преобразования переменных
     * @var array
     */
    protected $map = [];

    /**
     * Карта типов
     * @var array
     */
    protected $types = [
        's'  => PDO::PARAM_STR,
        'i'  => PDO::PARAM_INT,
        'b'  => PDO::PARAM_BOOL,
        'a'  => PDO::PARAM_STR,
        'as' => PDO::PARAM_STR,
        'ai' => PDO::PARAM_INT,
    ];

    /**
     * Конструктор
     *
     * @param PDO $db
     */
    protected function __construct(PDO $db)
    {
        $this->db = $db;
    }


    /**
     * Метод задает карту преобразования перменных
     *
     * @param array $map
     */
    public function setMap(array $map)
    {
        $this->map = $map;
    }

    /**
     * Метод привязывает параметры к значениям на основе карты
     *
     * @param array $params
     *
     * @throws PDOException
     */
    public function bindValueList(array $params)
    {
        foreach ($this->map as $key => $data) {
            if (isset($params[$key])) {
                $bValue = $params[$key];
            } elseif (isset($params[':' . $key])) {
                $bValue = $params[':' . $key];
            } else {
                throw new PDOException("The value for :{$key} placeholder isn't found");
            }

            $type = array_shift($data);
            $bType = $this->types[$type];

            if ($type{0} === 'a') {
                foreach ($data as $bParam) {
                    $bVal = array_shift($bValue); //????
                    parent::bindValue($bParam, $bVal, $bType); //????
                }
            } else {
                foreach ($data as $bParam) {
                    parent::bindValue($bParam, $bValue, $bType); //????
                }
            }
        }
    }

    /**
     * Метод расширяет PDOStatement::execute()
     *
     * @param array|null $params
     *
     * @return bool
     */
    public function execute($params = null)
    {
        if (is_array($params) && ! empty($params)) {
            $this->bindValueList($params);
        }
        $start = microtime(true);
        $result = parent::execute();
        $this->db->saveQuery($this->queryString, microtime(true) - $start);
        return $result;
    }
}
