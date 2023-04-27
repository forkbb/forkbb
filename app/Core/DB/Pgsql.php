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

class Pgsql
{
    /**
     * Массив замены типов полей таблицы
     */
    protected array $dbTypeRepl = [
        '%^(?:TINY|SMALL)INT(?:\s*\(\d+\))?(?:\s*UNSIGNED)?$%i'          => 'SMALLINT',
        '%^(?:MEDIUM)?INT(?:\s*\(\d+\))?(?:\s*UNSIGNED)?$%i'             => 'INTEGER',
        '%^BIGINT(?:\s*\(\d+\))?(?:\s*UNSIGNED)?$%i'                     => 'BIGINT',
        '%^(?:TINY|MEDIUM|LONG)TEXT$%i'                                  => 'TEXT',
        '%^DOUBLE(?:\s+PRECISION)?(?:\s*\([\d,]+\))?(?:\s*UNSIGNED)?$%i' => 'DOUBLE PRECISION',
        '%^(?:FLOAT|REAL)(?:\s*\([\d,]+\))?(?:\s*UNSIGNED)?$%i'          => 'REAL',
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
        'real'      => 'i',
        'double'    => 'i',
        'double precision' => 'i',
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
        if ('::' === \substr($name, 0, 2)) {
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
            throw new PDOException('Invalid data type for DEFAULT');
        }
    }

    /**
     * Формирует подстроку COLLATE
     */
    protected function buildCollate(array $data): string
    {
        $query = '';

        // сравнение
        if (\preg_match('%^(?:CHAR|VARCHAR|TINYTEXT|TEXT|MEDIUMTEXT|LONGTEXT|ENUM|SET)\b%i', $data[0])) {
            $query .= ' COLLATE ';

            if (
                isset($data[3])
                && \is_string($data[3])
                && \preg_match('%bin%i', $data[3])
            ) {
                $query .= '"C"';
            } else {
                $query .= '"fork_icu"';
            }
        }

        return $query;
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
            // сравнение
            $query .= $this->buildCollate($data);
            // не NULL
            if (empty($data[1])) {
                $query .= ' NOT NULL';
            }
            // значение по умолчанию
            if (isset($data[2])) {
                $query .= ' DEFAULT ' . $this->convToStr($data[2]);
            }
        }

        return $query;
    }

    /**
     * Проверяет наличие таблицы в базе
     */
    public function tableExists(string $table): bool
    {
        $vars = [
            ':schema' => 'public',
            ':tname'  => $this->tName($table),
            ':ttype'  => 'r',
        ];
        $query = 'SELECT 1
            FROM pg_class AS c
            INNER JOIN pg_namespace AS n ON n.oid=c.relnamespace
            WHERE c.relname=?s:tname AND c.relkind=?s:ttype AND n.nspname=?s:schema';

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
            ':schema' => 'public',
            ':tname'  => $this->tName($table),
            ':ttype'  => 'r',
            ':fname'  => $field,
        ];
        $query = 'SELECT 1
            FROM pg_attribute AS a
            INNER JOIN pg_class AS c ON a.attrelid=c.oid
            INNER JOIN pg_namespace AS n ON n.oid=c.relnamespace
            WHERE a.attname=?s:fname AND c.relname=?s:tname AND c.relkind=?s:ttype AND n.nspname=?s:schema';

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
            ':schema' => 'public',
            ':tname'  => $table,
            ':ttype'  => 'r',
            ':iname'  => $table . '_' . ('PRIMARY' === $index ? 'pkey' : $index),
            ':itype'  => 'i',
        ];
        $query = 'SELECT 1
            FROM pg_class AS i
            INNER JOIN pg_index AS ix ON ix.indexrelid=i.oid
            INNER JOIN pg_class AS c ON c.oid=ix.indrelid
            INNER JOIN pg_namespace AS n ON n.oid=c.relnamespace
            WHERE i.relname=?s:iname
                AND i.relkind=?s:itype
                AND c.relname=?s:tname
                AND c.relkind=?s:ttype
                AND n.nspname=?s:schema';

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
        $query = "CREATE TABLE IF NOT EXISTS \"{$table}\" (";

        foreach ($schema['FIELDS'] as $field => $data) {
            $query .= $this->buildColumn($field, $data) . ', ';
        }

        if (isset($schema['PRIMARY KEY'])) {
            $query .= 'PRIMARY KEY (' . $this->replIdxs($schema['PRIMARY KEY']) . '), ';
        }

        $query  = \rtrim($query, ', ') . ")";
        $result = false !== $this->db->exec($query);

        // вынесено отдельно для сохранения имен индексов
        if ($result && isset($schema['UNIQUE KEYS'])) {
            foreach ($schema['UNIQUE KEYS'] as $key => $fields) {
                $result = $result && $this->addIndex($table, $key, $fields, true);
            }
        }

        if ($result && isset($schema['INDEXES'])) {
            foreach ($schema['INDEXES'] as $index => $fields) {
                $result = $result && $this->addIndex($table, $index, $fields, false);
            }
        }

        return $result;
    }

    /**
     * Удаляет таблицу
     */
    public function dropTable(string $table): bool
    {
        $table = $this->tName($table);

        return false !== $this->db->exec("DROP TABLE IF EXISTS \"{$table}\"");
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

        return false !== $this->db->exec("ALTER TABLE \"{$old}\" RENAME TO \"{$new}\"");
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

        $query = "ALTER TABLE \"{$table}\" ADD " . $this->buildColumn($field, [$type, $allowNull, $default, $collate]);

        return false !== $this->db->exec($query);
    }

    /**
     * Модифицирует поле в таблице
     */
    public function alterField(string $table, string $field, string $type, bool $allowNull, mixed $default = null, string $collate = null, string $after = null): bool
    {
        $this->nameCheck($field);

        $table = $this->tName($table);
        $query = "ALTER TABLE \"{$table}\"";

        if ('SERIAL' === \strtoupper($type) || $allowNull) {
            $query .= " ALTER COLUMN \"{$field}\" DROP NOT NULL,";
        }

        if ('SERIAL' === \strtoupper($type) || null === $default) {
            $query .= " ALTER COLUMN \"{$field}\" DROP DEFAULT,";
        }

        $query = " ALTER COLUMN \"{$field}\" TYPE "
            . $this->replType($type)
            . $this->buildCollate([$type, $allowNull, $default, $collate])
            . ','; // ???? Использовать USING?

        if ('SERIAL' !== \strtoupper($type) && ! $allowNull) {
            $query .= " ALTER COLUMN \"{$field}\" SET NOT NULL,";
        }

        if ('SERIAL' !== \strtoupper($type) && null !== $default) {
            $query .= " ALTER COLUMN \"{$field}\" SET DEFAULT " . $this->convToStr($default) . ',';
        }

        $query = \rtrim($query, ',');

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

        return false !== $this->db->exec("ALTER TABLE \"{$table}\" DROP COLUMN \"{$field}\"");
    }

    /**
     * Переименование поля в таблице
     */
    public function renameField(string $table, string $old, string $new): bool
    {
        $table = $this->tName($table);

        $this->nameCheck($old);
        $this->nameCheck($new);

        if (
            $this->fieldExists($table, $new)
            && ! $this->fieldExists($table, $old)
        ) {
            return true;
        }

        return false !== $this->db->exec("ALTER TABLE \"{$table}\" RENAME COLUMN \"{$old}\" TO \"{$new}\"");
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

        if ('PRIMARY' === $index) {
            $query = "ALTER TABLE \"{$table}\" ADD PRIMARY KEY (" . $this->replIdxs($fields) . ')';
        } else {
            $this->nameCheck($index);

            $unique = $unique ? 'UNIQUE' : '';
            $query  = "CREATE {$unique} INDEX \"{$table}_{$index}\" ON \"{$table}\" (" . $this->replIdxs($fields) . ')';
        }

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

        if ('PRIMARY' === $index) {
            $query = "ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$table}_pkey\"";
        } else {
            $this->nameCheck($index);

            $query = "DROP INDEX \"{$table}_{$index}\"";
        }

        return false !== $this->db->exec($query);
    }

    /**
     * Очищает таблицу
     */
    public function truncateTable(string $table): bool
    {
        $table = $this->tName($table);

        return false !== $this->db->exec("TRUNCATE TABLE ONLY \"{$table}\" RESTART IDENTITY");
    }

    /**
     * Возвращает статистику
     */
    public function statistics(): array
    {
        $records = $size = $tables = 0;

        $vars = [
            ':schema' => 'public',
            ':tname'  => \str_replace('_', '\\_', $this->dbPrefix) . '%',
        ];
        $query = 'SELECT c.relname, c.relpages, c.reltuples, c.relkind
            FROM pg_class AS c
            INNER JOIN pg_namespace AS n ON n.oid=c.relnamespace
            WHERE n.nspname=?s:schema AND c.relname LIKE ?s:tname';

        $stmt = $this->db->query($query, $vars);

        while ($row = $stmt->fetch()) {
            if ('r' === $row['relkind']) { // обычная таблица
                ++$tables;
                $records += $row['reltuples'];
            }

            $size += $row['relpages'];
        }

        $other = [
            'pg_database_size' => $this->db->query('SELECT pg_size_pretty(pg_database_size(current_database()))')->fetchColumn(),
        ]
        + $this->db->query('SELECT name, setting FROM pg_settings WHERE category ~ \'Locale\'')->fetchAll(PDO::FETCH_KEY_PAIR);
/*
        $stmt = $this->db->query('SHOW ALL');

        while ($row = $stmt->fetch()) {
            if ('block_size' === $row['name']) {
                $blockSize = (int) $row['setting'];
            } elseif (\preg_match('%^lc_|_encoding%', $row['name'])) {
                $other[$row['name']] = $row['setting'];
            }
        }
*/
        $blockSize = $this->db->query('SELECT current_setting(\'block_size\')')->fetchColumn();
        $size     *= $blockSize ?: 8192;

        return [
            'db'          => 'PostgreSQL (PDO) v.' . $this->db->getAttribute(PDO::ATTR_SERVER_VERSION),
            'tables'      => (string) $tables,
            'records'     => $records,
            'size'        => $size,
//            'server info' => $this->db->getAttribute(PDO::ATTR_SERVER_INFO),
        ] + $other;
    }

    /**
     * Формирует карту базы данных
     */
    public function getMap(): array
    {
        $vars = [
            ':schema' => 'public',
            ':tname'  => \str_replace('_', '\\_', $this->dbPrefix) . '%',
        ];
        $query = 'SELECT table_name, column_name, data_type
            FROM information_schema.columns
            WHERE table_catalog = current_database() AND table_schema = ?s:schema AND table_name LIKE ?s:tname
            ORDER BY table_name';

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
