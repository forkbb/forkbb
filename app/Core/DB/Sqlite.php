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

        $this->testStr($prefix);

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
     * Проверяет строку на допустимые символы
     */
    protected function testStr(string $str): void
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
                $this->testStr($matches[1]);

                $value = "\"{$matches[1]}\""; // {$matches[2]}
            } else {
                $this->testStr($value);

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
        $this->testStr($table);

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
        $this->testStr($table);

        $prKey = true;
        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $query = "CREATE TABLE IF NOT EXISTS \"{$table}\" (";

        foreach ($schema['FIELDS'] as $field => $data) {
            $this->testStr($field);
            // имя и тип
            $query .= "\"{$field}\" " . $this->replType($data[0]);

            if ('SERIAL' === \strtoupper($data[0])) {
                $prKey = false;
            } else {
                // сравнение
                if (\preg_match('%^(?:CHAR|VARCHAR|TINYTEXT|TEXT|MEDIUMTEXT|LONGTEXT|ENUM|SET)%i', $data[0])) {
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
                // не NULL
                if (empty($data[1])) {
                    $query .= ' NOT NULL';
                }
                // значение по умолчанию
                if (isset($data[2])) {
                    $query .= ' DEFAULT ' . $this->convToStr($data[2]);
                }
            }

            $query .= ', ';
        }

        if ($prKey && isset($schema['PRIMARY KEY'])) { // если не было поля с типом SERIAL
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
        $this->testStr($table);

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        return false !== $this->db->exec("DROP TABLE IF EXISTS \"{$table}\"");
    }

    /**
     * Переименовывает таблицу
     */
    public function renameTable(string $old, string $new, bool $noPrefix = false): bool
    {
        $this->testStr($old);
        $this->testStr($new);

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
        $this->testStr($table);
        $this->testStr($field);

        if ($this->fieldExists($table, $field, $noPrefix)) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $query = "ALTER TABLE \"{$table}\" ADD COLUMN \"{$field}\" " . $this->replType($type);

        if ('SERIAL' !== \strtoupper($type)) {
            if (! $allowNull) {
                $query .= ' NOT NULL';
            }

            if (null !== $default) {
                $query .= ' DEFAULT ' . $this->convToStr($default);
            }
        }

        return false !== $this->db->exec($query);
    }

    /**
     * Модифицирует поле в таблице
     */
    public function alterField(string $table, string $field, string $type, bool $allowNull, /* mixed */ $default = null, string $after = null, bool $noPrefix = false): bool
    {
        $this->testStr($table);
        $this->testStr($field);

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

		return true; // ???????????????????????????????????????
    }

    /**
     * Удаляет поле из таблицы
     */
    public function dropField(string $table, string $field, bool $noPrefix = false): bool
    {
        $this->testStr($table);
        $this->testStr($field);

        if (! $this->fieldExists($table, $field, $noPrefix)) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        return false !== $this->db->exec("ALTER TABLE \"{$table}\" DROP COLUMN \"{$field}\""); // ???? add 2021-03-12 (3.35.0)
    }

    /**
     * Переименование поля в таблице
     */
    public function renameField(string $table, string $old, string $new, bool $noPrefix = false): bool
    {
        $this->testStr($table);
        $this->testStr($old);
        $this->testStr($new);

        if (
            $this->fieldExists($table, $new, $noPrefix)
            && ! $this->fieldExists($table, $old, $noPrefix)
        ) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        return false !== $this->db->exec("ALTER TABLE \"{$table}\" RENAME COLUMN \"{$old}\" TO \"{$new}\""); // ???? add 2018-09-15 (3.25.0)
    }

    /**
     * Добавляет индекс в таблицу
     */
    public function addIndex(string $table, string $index, array $fields, bool $unique = false, bool $noPrefix = false): bool
    {
        $this->testStr($table);

        if ($this->indexExists($table, $index, $noPrefix)) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;

        if ('PRIMARY' === $index) {
            // ?????
        } else {
            $index  = $table . '_' . $index;

            $this->testStr($index);

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
        $this->testStr($table);

        if (! $this->indexExists($table, $index, $noPrefix)) {
            return true;
        }

        $table = ($noPrefix ? '' : $this->dbPrefix) . $table;
        $index = $table . '_' . ('PRIMARY' === $index ? 'pkey' : $index);

        $this->testStr($index);

        return false !== $this->db->exec("DROP INDEX \"{$index}\"");
    }

    /**
     * Очищает таблицу
     */
    public function truncateTable(string $table, bool $noPrefix = false): bool
    {
        $this->testStr($table);

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
            ':tname'  => \str_replace('_', '\\_', $this->dbPrefix) . '%',
            ':ttype'  => 'table',
        ];
        $query = 'SELECT COUNT(*) FROM sqlite_master WHERE tbl_name LIKE ?s:tname ESCAPE \'\\\' AND type=?s:ttype';

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
            ':tname' => \str_replace('_', '\\_', $this->dbPrefix) . '%',
        ];
        $query = 'SELECT m.name AS table_name, p.name AS column_name, p.type AS data_type
            FROM sqlite_master AS m
            INNER JOIN pragma_table_info(m.name) AS p
            WHERE table_name LIKE ?s:tname ESCAPE \'\\\'
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
