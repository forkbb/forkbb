<?php

namespace ForkBB\Core\DB;

use ForkBB\Core\DB;
use PDO;
use PDOStatement;
use PDOException;

class Mysql
{
    /**
     * @var DB
     */
    protected $db;

    /**
     * Префикс для таблиц базы
     * @var string
     */
    protected $dbPrefix;

    /**
     * Массив замены типов полей таблицы
     * @var array
     */
    protected $dbTypeRepl = [
        '%^SERIAL$%i' => 'INT(10) UNSIGNED AUTO_INCREMENT',
    ];

    /**
     * Подстановка типов полей для карты БД
     * @var array
     */
    protected $types = [
        'bool'      => 'b',
        'boolean'   => 'b',
        'tinyint'   => 'i',
        'smallint'  => 'i',
        'mediumint' => 'i',
        'int'       => 'i',
        'integer'   => 'i',
        'bigint'    => 'i',
        'decimal'   => 'i',
        'dec'       => 'i',
        'float'     => 'i',
        'double'    => 'i',
    ];

    /**
     * Конструктор
     *
     * @param DB $db
     * @param string $prefix
     */
    public function __construct(DB $db, $prefix)
    {
        $this->db = $db;
        $this->dbPrefix = $prefix;
    }

    /**
     * Перехват неизвестных методов
     *
     * @param string $name
     * @param array $args
     *
     * @throws PDOException
     */
    public function __call($name, array $args)
    {
        throw new PDOException("Method '{$name}' not found in DB driver.");
    }

    /**
     * Проверяет строку на допустимые символы
     *
     * @param string $str
     *
     * @throws PDOException
     */
    protected function testStr($str)
    {
        if (! is_string($str) || preg_match('%[^a-zA-Z0-9_]%', $str)) {
            throw new PDOException("Name '{$str}' have bad characters.");
        }
    }

    /**
     * Операции над полями индексов: проверка, замена
     *
     * @param array $arr
     *
     * @return string
     */
    protected function replIdxs(array $arr)
    {
        foreach ($arr as &$value) {
            if (preg_match('%^(.*)\s*(\(\d+\))$%', $value, $matches)) {
                $this->testStr($matches[1]);
                $value = "`{$matches[1]}`{$matches[2]}";
            } else {
                $this->testStr($value);
                $value = "`{$value}`";
            }
            unset($value);
        }
        return implode(',', $arr);
    }

    /**
     * Замена типа поля в соответствии с dbTypeRepl
     *
     * @param string $type
     *
     * @return string
     */
    protected function replType($type)
    {
        return preg_replace(array_keys($this->dbTypeRepl), array_values($this->dbTypeRepl), $type);
    }

    /**
     * Конвертирует данные в строку для DEFAULT
     *
     * @param mixed $data
     *
     * @throws PDOException
     *
     * @return string
     */
    protected function convToStr($data) {
        if (is_string($data)) {
            return $this->db->quote($data);
        } elseif (is_numeric($data)) {
            return (string) $data;
        } elseif (is_bool($data)) {
            return $data ? 'true' : 'false';
        } else {
            throw new PDOException('Invalid data type for DEFAULT.');
        }
    }

    /**
     * Проверяет наличие таблицы в базе
     *
     * @param string $table
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function tableExists($table, $noPrefix = false)
    {
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        try {
            $stmt = $this->db->query('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s:table', [':table' => $table]);
            $result = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            return false;
        }
        return ! empty($result);
    }

    /**
     * Проверяет наличие поля в таблице
     *
     * @param string $table
     * @param string $field
     * @param bool $noPrefix
     *
     * @return bool
     */
	public function fieldExists($table, $field, $noPrefix = false)
	{
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        try {
            $stmt = $this->db->query('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s:table AND COLUMN_NAME = ?s:field', [':table' => $table, ':field' => $field]);
            $result = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            return false;
        }
        return ! empty($result);
	}

    /**
     * Проверяет наличие индекса в таблице
     *
     * @param string $table
     * @param string $index
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function indexExists($table, $index, $noPrefix = false)
    {
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $index = $index == 'PRIMARY' ? $index : $table . '_' . $index;
        try {
            $stmt = $this->db->query('SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s:table AND INDEX_NAME = ?s:index', [':table' => $table, ':index' => $index]);
            $result = $stmt->fetch();
            $stmt->closeCursor();
        } catch (PDOException $e) {
            return false;
        }
        return ! empty($result);
    }

    /**
     * Создает таблицу
     *
     * @param string $table
     * @param array $schema
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function createTable($table, array $schema, $noPrefix = false)
    {
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $this->testStr($table);
        $query = "CREATE TABLE IF NOT EXISTS `{$table}` (";
        foreach ($schema['FIELDS'] as $field => $data) {
            $this->testStr($field);
            // имя и тип
            $query .= "`{$field}` " . $this->replType($data[0]);
            // сравнение
            if (preg_match('%^(?:CHAR|VARCHAR|TINYTEXT|TEXT|MEDIUMTEXT|LONGTEXT|ENUM|SET)%i', $data[0])) {
                $query .= ' CHARACTER SET utf8mb4 COLLATE utf8mb4_';
                if (isset($data[3]) && is_string($data[3])) {
                    $this->testStr($data[3]);
                    $query .= $data[3];
                } else {
                    $query .= 'unicode_ci';
                }
            }
            // не NULL
            if (empty($data[1])) {
                $query .= ' NOT NULL';
            }
            // значение по умолчанию
            if (isset($data[2])) {
                $query .= ' DEFAULT ' . $this->convToStr($data[2]);
            }
            $query .= ', ';
        }
        if (isset($schema['PRIMARY KEY'])) {
            $query .= 'PRIMARY KEY (' . $this->replIdxs($schema['PRIMARY KEY']) . '), ';
        }
        if (isset($schema['UNIQUE KEYS'])) {
            foreach ($schema['UNIQUE KEYS'] as $key => $fields) {
                $this->testStr($key);
                $query .= "UNIQUE `{$table}_{$key}` (" . $this->replIdxs($fields) . '), ';
            }
        }
        if (isset($schema['INDEXES'])) {
            foreach ($schema['INDEXES'] as $index => $fields) {
                $this->testStr($index);
                $query .= "INDEX `{$table}_{$index}` (" . $this->replIdxs($fields) . '), ';
            }
        }
        if (isset($schema['ENGINE'])) {
            $engine = $schema['ENGINE'];
        } else {
            // при отсутствии типа таблицы он определяется на основании типов других таблиц в базе
            $prefix = str_replace('_', '\\_', $this->dbPrefix);
            $stmt = $this->db->query("SHOW TABLE STATUS LIKE '{$prefix}%'");
            $engine = [];
            while ($row = $stmt->fetch()) {
                if (isset($engine[$row['Engine']])) {
                    ++$engine[$row['Engine']];
                } else {
                    $engine[$row['Engine']] = 1;
                }
            }
            // в базе нет таблиц
            if (empty($engine)) {
                $engine = 'MyISAM';
            } else {
                arsort($engine);
                // берем тип наиболее часто встречаемый у имеющихся таблиц
                $engine = array_keys($engine);
                $engine = array_shift($engine);
            }
        }
        $this->testStr($engine);
        $query = rtrim($query, ', ') . ") ENGINE={$engine} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        return $this->db->exec($query) !== false;
    }

    /**
     * Удаляет таблицу
     *
     * @param string $table
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function dropTable($table, $noPrefix = false)
    {
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $this->testStr($table);
		return $this->db->exec("DROP TABLE IF EXISTS `{$table}`") !== false;
    }

    /**
     * Переименовывает таблицу
     *
     * @param string $old
     * @param string $new
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function renameTable($old, $new, $noPrefix = false)
    {
        if ($this->tableExists($new, $noPrefix) && ! $this->tableExists($old, $noPrefix)) {
            return true;
        }
        $old = ($noPrefix ? '' : $this->dbPrefix) . $old;
        $this->testStr($old);
        $new = ($noPrefix ? '' : $this->dbPrefix) . $new;
        $this->testStr($new);
        return $this->db->exec("ALTER TABLE `{$old}` RENAME TO `{$new}`") !== false;
    }

    /**
     * Добавляет поле в таблицу
     *
     * @param string $table
     * @param string $field
     * @param bool $allowNull
     * @param mixed $default
     * @param string $after
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function addField($table, $field, $type, $allowNull, $default = null, $after = null, $noPrefix = false)
    {
        if ($this->fieldExists($table, $field, $noPrefix)) {
            return true;
        }
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $this->testStr($table);
        $this->testStr($field);
        $query = "ALTER TABLE `{$table}` ADD `{$field}` " . $this->replType($type);
        if ($allowNull) {
            $query .= ' NOT NULL';
        }
        if (null !== $default) {
            $query .= ' DEFAULT ' . $this->convToStr($default);
        }
        if (null !== $after) {
            $this->testStr($after);
            $query .= " AFTER `{$after}`";
        }
        return $this->db->exec($query) !== false;
    }

    /**
     * Модифицирует поле в таблице
     *
     * @param string $table
     * @param string $field
     * @param bool $allowNull
     * @param mixed $default
     * @param string $after
     * @param bool $noPrefix
     *
     * @return bool
     */
	public function alterField($table, $field, $type, $allowNull, $default = null, $after = null, $noPrefix = false)
	{
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $this->testStr($table);
        $this->testStr($field);
        $query = "ALTER TABLE `{$table}` MODIFY `{$field}` " . $this->replType($type);
        if ($allowNull) {
            $query .= ' NOT NULL';
        }
        if (null !== $default) {
            $query .= ' DEFAULT ' . $this->convToStr($default);
        }
        if (null !== $after) {
            $this->testStr($after);
            $query .= " AFTER `{$after}`";
        }
        return $this->db->exec($query) !== false;
	}

    /**
     * Удаляет поле из таблицы
     *
     * @param string $table
     * @param string $field
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function dropField($table, $field, $noPrefix = false)
    {
        if (! $this->fieldExists($table, $field, $noPrefix)) {
            return true;
        }
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $this->testStr($table);
        $this->testStr($field);
        return $this->db->exec("ALTER TABLE `{$table}` DROP COLUMN `{$field}`") !== false;
    }

    /**
     * Добавляет индекс в таблицу
     *
     * @param string $table
     * @param string $index
     * @param array $fields
     * @param bool $unique
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function addIndex($table, $index, array $fields, $unique = false, $noPrefix = false)
    {
        if ($this->indexExists($table, $index, $noPrefix)) {
            return true;
        }
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $this->testStr($table);
        $query = "ALTER TABLE `{$table}` ADD ";
        if ($index == 'PRIMARY') {
            $query .= 'PRIMARY KEY';
        } else {
            $index = $table . '_' . $index;
            $this->testStr($index);
            if ($unique) {
                $query .= "UNIQUE `{$index}`";
            } else {
                $query .= "INDEX `{$index}`";
            }
        }
        $query .= ' (' . $this->replIdxs($fields) . ')';
        return $this->db->exec($query) !== false;
    }

    /**
     * Удаляет индекс из таблицы
     *
     * @param string $table
     * @param string $index
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function dropIndex($table, $index, $noPrefix = false)
    {
        if (! $this->indexExists($table, $index, $noPrefix)) {
            return true;
        }
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $this->testStr($table);
        $query = "ALTER TABLE `{$table}` ";
        if ($index == 'PRIMARY') {
            $query .= "DROP PRIMARY KEY";
        } else {
            $index = $table . '_' . $index;
            $this->testStr($index);
            $query .= "DROP INDEX `{$index}`";
        }
        return $this->db->exec($query) !== false;
    }

    /**
     * Очищает таблицу
     *
     * @param string $table
     * @param bool $noPrefix
     *
     * @return bool
     */
    public function truncateTable($table, $noPrefix = false)
    {
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $this->testStr($table);
        return $this->db->exec("TRUNCATE TABLE `{$table}`") !== false;
    }

    /**
     * Статистика
     *
     * @return array|string
     */
    public function statistics()
    {
        $this->testStr($this->dbPrefix);
        $prefix = str_replace('_', '\\_', $this->dbPrefix);
        $stmt = $this->db->query("SHOW TABLE STATUS LIKE '{$prefix}%'");
        $records = $size = 0;
        $engine = [];
        while ($row = $stmt->fetch()) {
            $records += $row['Rows'];
            $size += $row['Data_length'] + $row['Index_length'];
            if (isset($engine[$row['Engine']])) {
                ++$engine[$row['Engine']];
            } else {
                $engine[$row['Engine']] = 1;
            }
        }
        arsort($engine);
        $tmp = [];
        foreach ($engine as $key => $val) {
            $tmp[] = "{$key}({$val})";
        }

        $other = [];
        $stmt = $this->db->query("SHOW VARIABLES LIKE 'character\_set\_%'");
        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $other[$row[0]] = $row[1];
        }

        return [
            'db'      => 'MySQL (PDO) ' . $this->db->getAttribute(\PDO::ATTR_SERVER_VERSION) . ' : ' . implode(', ', $tmp),
            'records' => $records,
            'size'    => $size,
            'server info' => $this->db->getAttribute(\PDO::ATTR_SERVER_INFO),
        ] + $other;
    }

    /**
     * Формирует карту базы данных
     *
     * @return array
     */
    public function getMap()
    {
        $stmt = $this->db->query('SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ?s', ["{$this->dbPrefix}%"]);
        $result = [];
        $table = null;
        while ($row = $stmt->fetch()) {
            if ($table !== $row['TABLE_NAME']) {
                $table = $row['TABLE_NAME'];
                $tableNoPref = substr($table, strlen($this->dbPrefix));
                $result[$tableNoPref] = [];
            }
            $type = strtolower($row['DATA_TYPE']);
            $result[$tableNoPref][$row['COLUMN_NAME']] = isset($this->types[$type]) ? $this->types[$type] : 's';
        }
        return $result;
    }
}
