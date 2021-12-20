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

class Sqlite
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
        '%^.*?INT.*$%i'                           => 'INTEGER',
        '%^.*?(?:CHAR|CLOB|TEXT).*$%i'            => 'TEXT',
        '%^.*?BLOB.*$%i'                          => 'BLOB',
        '%^.*?(?:REAL|FLOA|DOUB).*$%i'            => 'REAL',
        '%^.*?(?:NUMERIC|DECIMAL).*$%i'           => 'NUMERIC',
        '%^.*?BOOL.*$%i'                          => 'BOOLEAN', // ???? не соответствует SQLite
        '%^SERIAL$%i'                             => 'INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL',
    ];

    /**
     * Подстановка типов полей для карты БД
     * @var array
     */
    protected $types = [
        'boolean' => 'b',
        'integer' => 'i',
        'real'    => 'f',
        'numeric' => 'f',
    ];

    public function __construct(DB $db, string $prefix)
    {
        $this->db = $db;

        $this->nameCheck($prefix);

        $this->dbPrefix = $prefix;
    }

    /**
     * Перехват неизвестных методов
     */
    public function __call(string $name, array $args)
    {
        throw new PDOException("Method '{$name}' not found in DB driver.");
    }

    /**
     * Проверяет имя таблицы/индекса/поля на допустимые символы
     */
    protected function nameCheck(string $str): void
    {
        if (\preg_match('%[^a-zA-Z0-9_]%', $str)) {
            throw new PDOException("Name '{$str}' have bad characters.");
        }
    }

    /**
     * Операции над полями индексов: проверка, замена
     */
    protected function replIdxs(array $arr): string
    {
        foreach ($arr as &$value) {
            if (\preg_match('%^(.*)\s*(\(\d+\))$%', $value, $matches)) {
                $this->nameCheck($matches[1]);

                $value = "\"{$matches[1]}\"";
            } else {
                $this->nameCheck($value);

                $value = "\"{$value}\"";
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
    protected function convToStr(/* mixed */ $data): string
    {
        if (\is_string($data)) {
            return $this->db->quote($data);
        } elseif (\is_numeric($data)) {
            return (string) $data;
        } elseif (\is_bool($data)) {
            return $data ? 'true' : 'false';
        } else {
            throw new PDOException('Invalid data type for DEFAULT.');
        }
    }

    /**
     * Формирует строку для одного поля таблицы
     */
    protected function buildColumn(string $name, array $data): string
    {
        $this->nameCheck($name);
        // имя и тип
        $query = '"' . $name . '" ' . $this->replType($data[0]);

        if ('SERIAL' !== \strtoupper($data[0])) {
            // не NULL
            if (empty($data[1])) {
                $query .= ' NOT NULL';
            }
            // значение по умолчанию
            if (isset($data[2])) {
                $query .= ' DEFAULT ' . $this->convToStr($data[2]);
            }
            // сравнение
            if (\preg_match('%^(?:CHAR|VARCHAR|TINYTEXT|TEXT|MEDIUMTEXT|LONGTEXT|ENUM|SET)\b%i', $data[0])) {
                $query .= ' COLLATE ';

                if (
                    isset($data[3])
                    && \is_string($data[3])
                    && \preg_match('%bin%i', $data[3])
                ) {
                    $query .= 'BINARY';
                } else {
                    $query .= 'NOCASE';
                }
            }
        }

        return $query;
    }

    /**
     * Проверяет наличие таблицы в базе
     */
    public function tableExists(string $table, bool $noPrefix = false): bool
    {
        $vars = [
            ':tname'  => ($noPrefix ? '' : $this->dbPrefix) . $table,
            ':ttype'  => 'table',
        ];
        $query = 'SELECT 1 FROM sqlite_master WHERE tbl_name=?s:tname AND type=?s:ttype';

        $stmt   = $this->db->query($query, $vars);
        $result = $stmt->fetch();

        $stmt->closeCursor();

        return ! empty($result);
    }

    /**
     * Проверяет наличие поля в таблице
     */
    public function fieldExists(string $table, string $field, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $stmt  = $this->db->query("PRAGMA table_info({$table})");

        while ($row = $stmt->fetch()) {
            if ($field === $row['name']) {
                $stmt->closeCursor();

                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет наличие индекса в таблице
     */
    public function indexExists(string $table, string $index, bool $noPrefix = false): bool
    {
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        $vars = [
            ':tname'  => $table,
            ':iname'  => $table . '_' . $index, // ???? PRIMARY KEY искать нужно не в sqlite_master!
            ':itype'  => 'index',
        ];
        $query = 'SELECT 1 FROM sqlite_master WHERE name=?s:iname AND tbl_name=?s:tname AND type=?s:itype';

        $stmt   = $this->db->query($query, $vars);
        $result = $stmt->fetch();

        $stmt->closeCursor();

        return ! empty($result);
    }

    /**
     * Создает таблицу
     */
    public function createTable(string $table, array $schema, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $query = "CREATE TABLE IF NOT EXISTS \"{$table}\" (";

        foreach ($schema['FIELDS'] as $field => $data) {
            $query .= $this->buildColumn($field, $data) . ', ';
        }

        if (
            isset($schema['PRIMARY KEY'])
            && false === \strpos($query, 'PRIMARY KEY') // если не было поля с типом SERIAL
        ) {
            $query .= 'PRIMARY KEY (' . $this->replIdxs($schema['PRIMARY KEY']) . '), ';
        }

        $query  = \rtrim($query, ', ') . ")";
        $result = false !== $this->db->exec($query);

        // вынесено отдельно для сохранения имен индексов
        if ($result && isset($schema['UNIQUE KEYS'])) {
            foreach ($schema['UNIQUE KEYS'] as $key => $fields) {
                $result = $result && $this->addIndex($table, $key, $fields, true, true);
            }
        }

        if ($result && isset($schema['INDEXES'])) {
            foreach ($schema['INDEXES'] as $index => $fields) {
                $result = $result && $this->addIndex($table, $index, $fields, false, true);
            }
        }

        return $result;
    }

    /**
     * Удаляет таблицу
     */
    public function dropTable(string $table, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        return false !== $this->db->exec("DROP TABLE IF EXISTS \"{$table}\"");
    }

    /**
     * Переименовывает таблицу
     */
    public function renameTable(string $old, string $new, bool $noPrefix = false): bool
    {
        $this->nameCheck($old);
        $this->nameCheck($new);

        if (
            $this->tableExists($new, $noPrefix)
            && ! $this->tableExists($old, $noPrefix)
        ) {
            return true;
        }

        $old = ($noPrefix ? '' : $this->dbPrefix) . $old;
        $new = ($noPrefix ? '' : $this->dbPrefix) . $new;

        return false !== $this->db->exec("ALTER TABLE \"{$old}\" RENAME TO \"{$new}\"");
    }

    /**
     * Добавляет поле в таблицу // ???? нет COLLATE
     */
    public function addField(string $table, string $field, string $type, bool $allowNull, /* mixed */ $default = null, string $after = null, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);

        if ($this->fieldExists($table, $field, $noPrefix)) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $query = "ALTER TABLE \"{$table}\" ADD COLUMN " . $this->buildColumn($field, [$type, $allowNull, $default]);

        return false !== $this->db->exec($query);
    }

    /**
     * Модифицирует поле в таблице
     */
    public function alterField(string $table, string $field, string $type, bool $allowNull, /* mixed */ $default = null, string $after = null, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);
        $this->nameCheck($field);

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

		return true; // ???????????????????????????????????????
    }

    /**
     * Удаляет поле из таблицы
     */
    public function dropField(string $table, string $field, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);
        $this->nameCheck($field);

        if (! $this->fieldExists($table, $field, $noPrefix)) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        if (\version_compare($this->db->getAttribute(PDO::ATTR_SERVER_VERSION), '3.36.0', '>=')) { // 3.35.1 and 3.35.5 have fixes
            return false !== $this->db->exec("ALTER TABLE \"{$table}\" DROP COLUMN \"{$field}\""); // add 2021-03-12 (3.35.0)
        }

        $stmt = $this->db->query("PRAGMA table_info({$table})");

        $fields = [];

        while ($row = $stmt->fetch()) {
            $fields[$row['name']] = $row['name'];
        }

        unset($fields[$field]);

        $vars = [
            ':tname' => $table,
        ];
        $query = 'SELECT * FROM sqlite_master WHERE tbl_name=?s:tname';

        $stmt = $this->db->query($query, $vars);

        $createQuery = null;
        $otherQuery  = [];

        while ($row = $stmt->fetch()) {
            switch ($row['type']) {
                case 'table':
                    $createQuery = $row['sql'];

                    break;
                default:
                    if (! empty($row['sql'])) {
                        $otherQuery[$row['name']] = $row['sql'];
                    }

                    break;
            }
        }

        $tableTmp    = $table . '_tmp' . \time();
        $createQuery = \preg_replace("%(CREATE\s+TABLE\s+\"?){$table}\b%", '${1}' . $tableTmp, $createQuery, -1, $count);

        $result = 1 === $count;

        $tmp         = \implode('|', $fields);
        $createQuery = \preg_replace_callback(
            "%[(,]\s*(?:\"{$field}\"|\b{$field}\b).*?(?:,(?=\s*(?:\"?\b(?:{$tmp})|CONSTRAINT|PRIMARY|CHECK|FOREIGN))|\)(?=\s*(?:$|WITHOUT|STRICT)))%si",
            function ($matches) {
                if ('(' === $matches[0][0]) {
                    return '(';
                } elseif (')' === $matches[0][-1]) {
                    return ')';
                } else {
                    return ',';
                }
            },
            $createQuery,
            -1,
            $count
        );

        $result = $result && 1 === $count;
        $result = $result && false !== $this->db->exec($createQuery);

        $tmp   = '"' . \implode('", "', $fields) . '"';
        $query = "INSERT INTO \"{$tableTmp}\" ({$tmp})
            SELECT {$tmp}
            FROM \"{$table}\"";

        $result = $result && false !== $this->db->exec($query);
        $result = $result && $this->dropTable($table, true);
        $result = $result && $this->renameTable($tableTmp, $table, true);

        foreach ($otherQuery as $query) {
            if (! \preg_match("%\([^)]*?\b{$field}\b%", $query)) {
                $result = $result && false !== $this->db->exec($query);
            }
        }

        return $result;
    }

    /**
     * Переименование поля в таблице
     */
    public function renameField(string $table, string $old, string $new, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);
        $this->nameCheck($old);
        $this->nameCheck($new);

        if (
            $this->fieldExists($table, $new, $noPrefix)
            && ! $this->fieldExists($table, $old, $noPrefix)
        ) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        return false !== $this->db->exec("ALTER TABLE \"{$table}\" RENAME COLUMN \"{$old}\" TO \"{$new}\""); // add 2018-09-15 (3.25.0)
    }

    /**
     * Добавляет индекс в таблицу
     */
    public function addIndex(string $table, string $index, array $fields, bool $unique = false, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);

        if ($this->indexExists($table, $index, $noPrefix)) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        if ('PRIMARY' === $index) {
            // ?????
        } else {
            $index  = $table . '_' . $index;

            $this->nameCheck($index);

            $unique = $unique ? 'UNIQUE' : '';
            $query  = "CREATE {$unique} INDEX \"{$index}\" ON \"{$table}\" (" . $this->replIdxs($fields) . ')';
        }

        return false !== $this->db->exec($query);
    }

    /**
     * Удаляет индекс из таблицы
     */
    public function dropIndex(string $table, string $index, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);

        if (! $this->indexExists($table, $index, $noPrefix)) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $index = $table . '_' . ('PRIMARY' === $index ? 'pkey' : $index);

        $this->nameCheck($index);

        return false !== $this->db->exec("DROP INDEX \"{$index}\"");
    }

    /**
     * Очищает таблицу
     */
    public function truncateTable(string $table, bool $noPrefix = false): bool
    {
        $this->nameCheck($table);

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        if (false !== $this->db->exec("DELETE FROM \"{$table}\"")) {
            $vars = [
                ':tname' => $table,
            ];
            $query = 'DELETE FROM SQLITE_SEQUENCE WHERE name=?s:tname';

            return false !== $this->db->exec($query, $vars);
        }

        return false;
    }

    /**
     * Возвращает статистику
     */
    public function statistics(): array
    {
        $vars = [
            ':tname'  => \str_replace('_', '#_', $this->dbPrefix) . '%',
            ':ttype'  => 'table',
        ];
        $query = 'SELECT COUNT(tbl_name) FROM sqlite_master WHERE tbl_name LIKE ?s:tname ESCAPE \'#\' AND type=?s:ttype';

        $tables  = $this->db->query($query, $vars)->fetchColumn();

        $records = 0;
        $size    = (int) $this->db->query('PRAGMA page_count;')->fetchColumn();
        $size   *= (int) $this->db->query('PRAGMA page_size;')->fetchColumn();

        return [
            'db'           => 'SQLite (PDO) v.' . $this->db->getAttribute(PDO::ATTR_SERVER_VERSION),
            'tables'       => (string) $tables,
            'records'      => $records,
            'size'         => $size,
#            'server info'  => $this->db->getAttribute(PDO::ATTR_SERVER_INFO),
            'encoding'     => $this->db->query('PRAGMA encoding;')->fetchColumn(),
            'journal_mode' => $this->db->query('PRAGMA journal_mode;')->fetchColumn(),
            'synchronous'  => $this->db->query('PRAGMA synchronous;')->fetchColumn(),
            'busy_timeout' => $this->db->query('PRAGMA busy_timeout;')->fetchColumn(),
        ];
    }

    /**
     * Формирует карту базы данных
     */
    public function getMap(): array
    {
        $vars = [
            ':tname' => \str_replace('_', '#_', $this->dbPrefix) . '%',
        ];
        $query = 'SELECT m.name AS table_name, p.name AS column_name, p.type AS data_type
            FROM sqlite_master AS m
            INNER JOIN pragma_table_info(m.name) AS p
            WHERE table_name LIKE ?s:tname ESCAPE \'#\'
            ORDER BY m.name, p.cid';

        $stmt   = $this->db->query($query, $vars);
        $result = [];
        $table  = null;
        $prfLen = \strlen($this->dbPrefix);

        while ($row = $stmt->fetch()) {
            if ($table !== $row['table_name']) {
                $table                = $row['table_name'];
                $tableNoPref          = \substr($table, $prfLen);
                $result[$tableNoPref] = [];
            }

            $type = \strtolower($row['data_type']);
            $result[$tableNoPref][$row['column_name']] = $this->types[$type] ?? 's';
        }

        return $result;
    }
}
