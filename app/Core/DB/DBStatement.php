<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core\DB;

use ForkBB\Core\DB;
use PDO;
use PDOStatement;
use PDOException;

class DBStatement
{
    const BOOLEAN = 'b';
    const FLOAT   = 'f';
    const INTEGER = 'i';
    const STRING  = 's';

    /**
     * Карта преобразования переменных
     */
    protected array $map = [];

    /**
     * Карта типов
     */
    protected array $types = [
        'b'  => PDO::PARAM_BOOL,
        'f'  => PDO::PARAM_STR,
        'i'  => PDO::PARAM_INT,
        's'  => PDO::PARAM_STR,
        'a'  => PDO::PARAM_STR,
        'ai' => PDO::PARAM_INT,
        'as' => PDO::PARAM_STR,
    ];

    public function __construct(protected DB $db, protected PDOStatement $stmt)
    {
    }

    /**
     * Метод задает карту преобразования перменных
     */
    public function setMap(array $map): void
    {
        $this->map = $map;
    }

    /**
     * Метод привязывает параметры к значениям на основе карты
     */
    public function bindValueList(array $params): void
    {
        foreach ($this->map as $key => $data) {
            $type   = \array_shift($data);
            $bType  = $this->types[$type];
            $bValue = $this->db->getValue($key, $params);

            if ('a' === $type[0]) {
                if (! \is_array($bValue)) {
                    throw new PDOException("Expected array: key='{$key}'");
                }

                foreach ($data as $bParam) {
                    $this->stmt->bindValue($bParam, \array_shift($bValue), $bType); //????
                }

            } else {
                foreach ($data as $bParam) {
                    $this->stmt->bindValue($bParam, $bValue, $bType); //????
                }
            }
        }
    }

    /**
     * Метод расширяет PDOStatement::execute()
     */
    public function execute(?array $params = null): bool
    {
        if (! empty($params)) {
            $this->bindValueList($params);
        }

        $start  = \microtime(true);
        $result = $this->stmt->execute();

        $this->db->saveQuery($this->stmt->queryString, \microtime(true) - $start);

        return $result;
    }

    /**
     * Передает вызовы метода в PDOStatement
     */
    public function __call(string $name, array $args): mixed
    {
        return $this->stmt->$name(...$args);
    }
}
