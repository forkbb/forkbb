<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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

class Mysql
{
    /**
     * Массив замены типов полей таблицы
     */
    protected array $dbTypeRepl = [
        '%^SERIAL$%i' => 'INT(10) UNSIGNED AUTO_INCREMENT',
    ];

    /**
     * Подстановка типов полей для карты БД
     */
    protected array $types = [
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

    public function __construct(protected DB $db, protected string $dbPrefix)
    {
        $this->nameCheck($dbPrefix);
    }

    /**
     * Перехват неизвестных методов
     */
    public function __call(string $name, array $args)
    {
        throw new PDOException("Method '{$name}' not found in DB driver");
    }

    /**
     * Проверяет имя таблицы/индекса/поля на допустимые символы
     */
    protected function nameCheck(string $str): void
    {
        if (\preg_match('%[^\w]%', $str)) {
            throw new PDOException("Name '{$str}' have bad characters");
        }
    }

    /**
     * Обрабатывает имя таблицы с одновременной проверкой
     */
    protected function tName(string $name): string
    {
        if (\str_starts_with($name, '::')) {
            $name = $this->dbPrefix . \substr($name, 2);
        }

        $this->nameCheck($name);

        return $name;
    }

    /**
     * Операции над полями индексов: проверка, замена
     */
    protected function replIdxs(array $arr): string
    {
        foreach ($arr as &$value) {
            if (\preg_match('%^(.*)\s*(\(\d+\))$%', $value, $matches)) {
                $this->nameCheck($matches[1]);

                $value = "`{$matches[1]}`{$matches[2]}";
            } else {
                $this->nameCheck($value);

                $value = "`{$value}`";
            }
        }

        unset($value);

        return \implode(',', $arr);
    }

    /**
     * Замена типа поля в соответствии с dbTypeRepl
     */
    protected function replType(string $type): string
    {
        return \preg_replace(\array_keys($this->dbTypeRepl), \array_values($this->dbTypeRepl), $type);
    }

    /**
     * Конвертирует данные в строку для DEFAULT
     */
    protected function convToStr(mixed $data): string
    {
        if (\is_string($data)) {
            return $this->db->quote($data);
        } elseif (\is_numeric($data)) {
            return (string) $data;
        } elseif (\is_bool($data)) {
            return $data ? 'true' : 'false';
        } else {
            throw new PDOException('Invalid data type for DEFAULT');
        }
    }

    /**
     * Формирует строку для одного поля таблицы
     */
    protected function buildColumn(string $name, array $data): string
    {
        $this->nameCheck($name);
        // имя и тип
        $query = '`' . $name . '` ' . $this->replType($data[0]);
        // сравнение
        if (\preg_match('%^(?:CHAR|VARCHAR|TINYTEXT|TEXT|MEDIUMTEXT|LONGTEXT|ENUM|SET)\b%i', $data[0])) {
            $query .= ' CHARACTER SET utf8mb4 COLLATE utf8mb4_';

            if (
                isset($data[3])
                && \is_string($data[3])
            ) {
                $this->nameCheck($data[3]);

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

        return $query;
    }

    /**
     * Проверяет наличие таблицы в базе
     */
    public function tableExists(string $table): bool
    {
        $vars = [
            ':tname' => $this->tName($table),
        ];
        $query = 'SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s:tname';

        $stmt   = $this->db->query($query, $vars);
        $result = $stmt->fetch();

        $stmt->closeCursor();

        return ! empty($result);
    }

    /**
     * Проверяет наличие поля в таблице
     */
    public function fieldExists(string $table, string $field): bool
    {
        $vars = [
            ':tname' => $this->tName($table),
            ':fname' => $field,
        ];
        $query = 'SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s:tname AND COLUMN_NAME = ?s:fname';

        $stmt   = $this->db->query($query, $vars);
        $result = $stmt->fetch();

        $stmt->closeCursor();

        return ! empty($result);
    }

    /**
     * Проверяет наличие индекса в таблице
     */
    public function indexExists(string $table, string $index): bool
    {
        $table = $this->tName($table);
        $vars  = [
            ':tname' => $table,
            ':index' => 'PRIMARY' == $index ? $index : $table . '_' . $index,
        ];
        $query = 'SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s:tname AND INDEX_NAME = ?s:index';

        $stmt   = $this->db->query($query, $vars);
        $result = $stmt->fetch();

        $stmt->closeCursor();

        return ! empty($result);
    }

    /**
     * Создает таблицу
     */
    public function createTable(string $table, array $schema): bool
    {
        $table = $this->tName($table);
        $query = "CREATE TABLE IF NOT EXISTS `{$table}` (";

        foreach ($schema['FIELDS'] as $field => $data) {
            $query .= $this->buildColumn($field, $data) . ', ';
        }

        if (isset($schema['PRIMARY KEY'])) {
            $query .= 'PRIMARY KEY (' . $this->replIdxs($schema['PRIMARY KEY']) . '), ';
        }

        if (isset($schema['UNIQUE KEYS'])) {
            foreach ($schema['UNIQUE KEYS'] as $key => $fields) {
                $this->nameCheck($key);

                $query .= "UNIQUE `{$table}_{$key}` (" . $this->replIdxs($fields) . '), ';
            }
        }

        if (isset($schema['INDEXES'])) {
            foreach ($schema['INDEXES'] as $index => $fields) {
                $this->nameCheck($index);

                $query .= "INDEX `{$table}_{$index}` (" . $this->replIdxs($fields) . '), ';
            }
        }

        if (isset($schema['ENGINE'])) {
            $engine = $schema['ENGINE'];
        } else {
            // при отсутствии типа таблицы он определяется на основании типов других таблиц в базе
            $prefix = \str_replace('_', '\\_', $this->dbPrefix);
            $stmt   = $this->db->query("SHOW TABLE STATUS LIKE '{$prefix}%'");
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
                \arsort($engine);
                // берем тип наиболее часто встречаемый у имеющихся таблиц
                $engine = \array_keys($engine);
                $engine = \array_shift($engine);
            }
        }

        $this->nameCheck($engine);

        $query = \rtrim($query, ', ') . ") ENGINE={$engine} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

        return false !== $this->db->exec($query);
    }

    /**
     * Удаляет таблицу
     */
    public function dropTable(string $table): bool
    {
        $table = $this->tName($table);

        return false !== $this->db->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    /**
     * Переименовывает таблицу
     */
    public function renameTable(string $old, string $new): bool
    {
        $old = $this->tName($old);
        $new = $this->tName($new);

        if (
            $this->tableExists($new)
            && ! $this->tableExists($old)
        ) {
            return true;
        }

        return false !== $this->db->exec("ALTER TABLE `{$old}` RENAME TO `{$new}`");
    }

    /**
     * Добавляет поле в таблицу
     */
    public function addField(string $table, string $field, string $type, bool $allowNull, mixed $default = null, string $collate = null, string $after = null): bool
    {
        $table = $this->tName($table);

        if ($this->fieldExists($table, $field)) {
            return true;
        }

        $query = "ALTER TABLE `{$table}` ADD " . $this->buildColumn($field, [$type, $allowNull, $default, $collate]);

        if (null !== $after) {
            $this->nameCheck($after);

            $query .= " AFTER `{$after}`";
        }

        return false !== $this->db->exec($query);
    }

    /**
     * Модифицирует поле в таблице
     */
    public function alterField(string $table, string $field, string $type, bool $allowNull, mixed $default = null, string $collate = null, string $after = null): bool
    {
        $table = $this->tName($table);
        $query = "ALTER TABLE `{$table}` MODIFY " . $this->buildColumn($field, [$type, $allowNull, $default, $collate]);

        if (null !== $after) {
            $this->nameCheck($after);

            $query .= " AFTER `{$after}`";
        }

        return false !== $this->db->exec($query);
    }

    /**
     * Удаляет поле из таблицы
     */
    public function dropField(string $table, string $field): bool
    {
        $table = $this->tName($table);

        $this->nameCheck($field);

        if (! $this->fieldExists($table, $field)) {
            return true;
        }

        return false !== $this->db->exec("ALTER TABLE `{$table}` DROP COLUMN `{$field}`");
    }

    /**
     * Переименование поля в таблице
     */
    public function renameField(string $table, string $old, string $new): bool
    {
        $table = $this->tName($table);

        $this->nameCheck($old);

        if (
            $this->fieldExists($table, $new)
            && ! $this->fieldExists($table, $old)
        ) {
            return true;
        }

        $vars = [
            ':tname' => $table,
            ':fname' => $old,
        ];
        $query = 'SELECT COLUMN_DEFAULT, IS_NULLABLE, COLUMN_TYPE, COLLATION_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s:tname AND COLUMN_NAME = ?s:fname';

        $stmt   = $this->db->query($query, $vars);
        $result = $stmt->fetch();

        $stmt->closeCursor();

        $type      = $result['COLUMN_TYPE'];
        $allowNull = 'YES' == $result['IS_NULLABLE'];
        $default   = $result['COLUMN_DEFAULT'];
        $collate   = \str_replace('utf8mb4_', '', $result['COLLATION_NAME'], $count);

        if (1 !== $count) {
            throw new PDOException("Table - '{$table}', column - '{$old}', collate - '{$result['COLLATION_NAME']}'");
        }

        $query = "ALTER TABLE `{$table}` CHANGE COLUMN `{$old}` " . $this->buildColumn($new, [$type, $allowNull, $default, $collate]);

        return false !== $this->db->exec($query);
    }

    /**
     * Добавляет индекс в таблицу
     */
    public function addIndex(string $table, string $index, array $fields, bool $unique = false): bool
    {
        $table = $this->tName($table);

        if ($this->indexExists($table, $index)) {
            return true;
        }

        $query = "ALTER TABLE `{$table}` ADD ";

        if ('PRIMARY' == $index) {
            $query .= 'PRIMARY KEY';
        } else {
            $this->nameCheck($index);

            $type   = $unique ? 'UNIQUE' : 'INDEX';
            $query .= "{$type} `{$table}_{$index}`";
        }

        $query .= ' (' . $this->replIdxs($fields) . ')';

        return false !== $this->db->exec($query);
    }

    /**
     * Удаляет индекс из таблицы
     */
    public function dropIndex(string $table, string $index): bool
    {
        $table = $this->tName($table);

        if (! $this->indexExists($table, $index)) {
            return true;
        }

        $query = "ALTER TABLE `{$table}` DROP ";

        if ('PRIMARY' == $index) {
            $query .= "PRIMARY KEY";
        } else {
            $this->nameCheck($index);

            $query .= "INDEX `{$table}_{$index}`";
        }

        return false !== $this->db->exec($query);
    }

    /**
     * Очищает таблицу
     */
    public function truncateTable(string $table): bool
    {
        $table = $this->tName($table);

        return false !== $this->db->exec("TRUNCATE TABLE `{$table}`");
    }

    /**
     * Возвращает статистику
     */
    public function statistics(): array
    {
        $prefix  = \str_replace('_', '\\_', $this->dbPrefix);
        $stmt    = $this->db->query("SHOW TABLE STATUS LIKE '{$prefix}%'");
        $records = $size = 0;
        $engine  = [];

        while ($row = $stmt->fetch()) {
            $records += $row['Rows'];
            $size    += $row['Data_length'] + $row['Index_length'];

            if (isset($engine[$row['Engine']])) {
                ++$engine[$row['Engine']];
            } else {
                $engine[$row['Engine']] = 1;
            }
        }

        \arsort($engine);

        $tmp = [];

        foreach ($engine as $key => $val) {
            $tmp[] = "{$key}({$val})";
        }

        $other   = [];
        $queries = [
            "SHOW VARIABLES LIKE 'character\\_set\\_%'",
            "SHOW VARIABLES LIKE '%max\\_conn%'",
            "SHOW STATUS LIKE '%\\_conn%'",
        ];

        foreach ($queries as $query) {
            $stmt  = $this->db->query($query);

            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $other[$row[0]] = $row[1];
            }
        }

        return [
            'db'      => 'MySQL (PDO) v.' . $this->db->getAttribute(PDO::ATTR_SERVER_VERSION),
            'tables'  => \implode(', ', $tmp),
            'records' => $records,
            'size'    => $size,
            'server info' => $this->db->getAttribute(PDO::ATTR_SERVER_INFO),
        ] + $other;
    }

    /**
     * Формирует карту базы данных
     */
    public function getMap(): array
    {
        $vars = [
            ':tname' => \str_replace('_', '\\_', $this->dbPrefix) . '%',
        ];
        $query = 'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ?s:tname
            ORDER BY TABLE_NAME';

        $stmt   = $this->db->query($query, $vars);
        $result = [];
        $table  = null;
        $prfLen = \strlen($this->dbPrefix);

        while ($row = $stmt->fetch()) {
            if ($table !== $row['TABLE_NAME']) {
                $table                = $row['TABLE_NAME'];
                $tableNoPref          = \substr($table, $prfLen);
                $result[$tableNoPref] = [];
            }

            $type = \strtolower($row['DATA_TYPE']);
            $result[$tableNoPref][$row['COLUMN_NAME']] = $this->types[$type] ?? 's';
        }

        return $result;
    }
}
