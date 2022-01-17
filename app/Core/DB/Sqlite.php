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

    protected function vComp(string $version): bool
    {
        return \version_compare($this->db->getAttribute(PDO::ATTR_SERVER_VERSION), $version, '>=');
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
     * Строит структуру таблицы + запросы на создание индексов
     */
    protected function tableSchema(string $table): array
    {
        $fields = [];
        $stmt   = $this->db->query("PRAGMA table_info({$table})");

        while ($row = $stmt->fetch()) {
            $fields[$row['name']] = $row['name'];
        }

        if (empty($fields)) {
            throw new PDOException("No '{$table}' table data");
        }

        $vars   = [
            ':tname' => $table,
        ];
        $query  = 'SELECT * FROM sqlite_master WHERE tbl_name=?s:tname';
        $stmt   = $this->db->query($query, $vars);
        $result = [];

        while ($row = $stmt->fetch()) {
            switch ($row['type']) {
                case 'table':
                    $result['TABLE']['sql'] = $row['sql'];

                    break;
                default:
                    if (! empty($row['sql'])) {
                        $result[$row['name']] = $row['sql'];
                    }

                    break;
            }
        }

        if (empty($result['TABLE']['sql'])) {
            throw new PDOException("No '{$table}' table sql data");
        }

        if (! \preg_match("%^CREATE\s+TABLE\s+\"?{$table}\b.*?\((.+)\)[^()]*$%", $result['TABLE']['sql'], $matches)) {
            throw new PDOException("Bad sql in '{$table}' table");
        }

        $subSchema                 = $matches[1];
        $result['TABLE']['CREATE'] = \str_replace($subSchema, '_STRUCTURE_', $result['TABLE']['sql']);
        $result['TABLE']['FIELDS'] = [];
        $result['TABLE']['OTHERS'] = [];

        do {
            $tmp     = $fields ? '"?\b(?:' . \implode('|', $fields) . ')\b\"?|' : '';
            $pattern = "%^
                \s*
                (
                    (?:
                        {$tmp}
                        CONSTRAINT
                    |
                        PRIMARY
                    |
                        UNIQUE
                    |
                        CHECK
                    |
                        FOREIGN
                    )
                )
                .*?
                (?:
                    ,
                    (?=
                        \s*
                        (?:
                            {$tmp}
                            CONSTRAINT
                        |
                            PRIMARY
                        |
                            UNIQUE
                        |
                            CHECK
                        |
                            FOREIGN
                        )
                    )
                |
                    $
                )%x";

            if (! \preg_match($pattern, $subSchema, $matches)) {
                throw new PDOException("Bad subSchema in '{$table}' table: {$subSchema}");
            }

            $subSchema = \substr($subSchema, \strlen($matches[0]));
            $value     = \trim($matches[0], ' ,');
            $key       = $matches[1];

            switch ($key) {
                case 'CONSTRAINT':
                case 'PRIMARY':
                case 'UNIQUE':
                case 'CHECK':
                case 'FOREIGN':
                    $result['TABLE']['OTHERS'][] = $value;

                    break;
                default:
                    if (
                        '"' === $key[0]
                        && '"' === $key[-1]
                    ) {
                        $key = \substr($key, 1, -1);
                    }

                    if (! isset($fields[$key])) {
                        throw new PDOException("Bad field in '{$table}' table: {$key}");
                    }

                    $result['TABLE']['FIELDS'][$key] = $value;

                    unset($fields[$key]);

                    break;
            }
        } while ('' != \trim($subSchema));

        return $result;
    }

    /**
     * Создает временную таблицу
     */
    protected function createTmpTable(array $schema, string $table): ?string
    {
        $tmpTable    = $table . '_tmp' . \time();
        $createQuery = \str_replace($table, $tmpTable, $schema['TABLE']['CREATE'], $count);

        if (1 !== $count) {
            return null;
        }

        $structure   = \implode(', ', $schema['TABLE']['FIELDS'] + $schema['TABLE']['OTHERS']);
        $createQuery = \str_replace('_STRUCTURE_', $structure, $createQuery, $count);

        if (1 !== $count) {
            return null;
        }

        return false !== $this->db->exec($createQuery) ? $tmpTable : null;
    }

    /**
     * Пересоздает таблицу из временной с помощью insert запроса
     */
    protected function tmpToTable(array $schema, string $insertQuery): bool
    {
        if (! \preg_match('%^INSERT INTO "(.*?)".+FROM "(.*?)"%s', $insertQuery, $matches)) {
            return false;
        }

        $tmpTable = $matches[1];
        $table    = $matches[2];

        $result = false !== $this->db->exec($insertQuery);
        $result = $result && $this->dropTable($table);
        $result = $result && $this->renameTable($tmpTable, $table);

        foreach ($schema as $key => $query) {
            if ('TABLE' === $key) {
                continue;
            }

            $result = $result && false !== $this->db->exec($query);
        }

        return $result;
    }

    /**
     * Проверяет наличие таблицы в базе
     */
    public function tableExists(string $table): bool
    {
        $vars = [
            ':tname' => $this->tName($table),
            ':ttype' => 'table',
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
    public function fieldExists(string $table, string $field): bool
    {
        $table = $this->tName($table);
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
    public function indexExists(string $table, string $index): bool
    {
        $table = $this->tName($table);

        if ('PRIMARY' === $index) {
            $stmt = $this->db->query("PRAGMA table_info('{$table}')");

            while ($row = $stmt->fetch()) {
                if ($row['pk'] > 0) {
                    $stmt->closeCursor();

                    return true;
                }
            }

            return false;
        } else {
            $vars  = [
                ':tname'  => $table,
                ':iname'  => $table . '_' . $index,
                ':itype'  => 'index',
            ];
            $query = 'SELECT 1 FROM sqlite_master WHERE name=?s:iname AND tbl_name=?s:tname AND type=?s:itype';

            $stmt   = $this->db->query($query, $vars);
            $result = $stmt->fetch();

            $stmt->closeCursor();

            return ! empty($result);
        }
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
    public function addField(string $table, string $field, string $type, bool $allowNull, /* mixed */ $default = null, string $collate = null, string $after = null): bool
    {
        $table = $this->tName($table);

        if ($this->fieldExists($table, $field)) {
            return true;
        }

        $query = "ALTER TABLE \"{$table}\" ADD COLUMN " . $this->buildColumn($field, [$type, $allowNull, $default, $collate]);

        return false !== $this->db->exec($query);
    }

    /**
     * Модифицирует поле в таблице
     */
    public function alterField(string $table, string $field, string $type, bool $allowNull, /* mixed */ $default = null, string $collate = null, string $after = null): bool
    {
        $this->nameCheck($field);

        $table = $this->tName($table);

        if (! $this->fieldExists($table, $field)) {
            return false;
        }

        $schema                            = $this->tableSchema($table);
        $schema['TABLE']['FIELDS'][$field] = $this->buildColumn($field, [$type, $allowNull, $default, $collate]);
        $tmpTable                          = $this->createTmpTable($schema, $table);

        if (! \is_string($tmpTable)) {
            return false;
        }

        $tmp   = '"' . \implode('", "', \array_keys($schema['TABLE']['FIELDS'])) . '"';
        $query = "INSERT INTO \"{$tmpTable}\" ({$tmp})
            SELECT {$tmp}
            FROM \"{$table}\"";

        return $this->tmpToTable($schema, $query);
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
        // 3.35.1 and 3.35.5 have fixes
        if ($this->vComp('3.36.0')) {
            return false !== $this->db->exec("ALTER TABLE \"{$table}\" DROP COLUMN \"{$field}\""); // add 2021-03-12 (3.35.0)
        }

        $schema = $this->tableSchema($table);

        unset($schema['TABLE']['FIELDS'][$field]);

        $tmpTable = $this->createTmpTable($schema, $table);

        if (! \is_string($tmpTable)) {
            return false;
        }

        $tmp   = '"' . \implode('", "', \array_keys($schema['TABLE']['FIELDS'])) . '"';
        $query = "INSERT INTO \"{$tmpTable}\" ({$tmp})
            SELECT {$tmp}
            FROM \"{$table}\"";

        return $this->tmpToTable($schema, $query);
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

        return false !== $this->db->exec("ALTER TABLE \"{$table}\" RENAME COLUMN \"{$old}\" TO \"{$new}\""); // add 2018-09-15 (3.25.0)
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
            $schema                      = $this->tableSchema($table);
            $schema['TABLE']['OTHERS'][] = 'PRIMARY KEY (' . $this->replIdxs($fields) . ')';
            $tmpTable                    = $this->createTmpTable($schema, $table);

            if (! \is_string($tmpTable)) {
                return false;
            }

            $tmp   = '"' . \implode('", "', \array_keys($schema['TABLE']['FIELDS'])) . '"';
            $query = "INSERT INTO \"{$tmpTable}\" ({$tmp})
                SELECT {$tmp}
                FROM \"{$table}\"";

            return $this->tmpToTable($schema, $query);
        } else {
            $index  = $table . '_' . $index;

            $this->nameCheck($index);

            $unique = $unique ? 'UNIQUE' : '';
            $query  = "CREATE {$unique} INDEX \"{$index}\" ON \"{$table}\" (" . $this->replIdxs($fields) . ')';

            return false !== $this->db->exec($query);
        }
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
            $schema = $this->tableSchema($table);

            foreach ($schema['TABLE']['FIELDS'] as &$value) {
                $value = \preg_replace(
                    '%\bPRIMARY\s+KEY\s+(?:(?:ASC|DESC)\s+)?(?:ON\s+CONFLICT\s+(?:ROLLBACK|ABORT|FAIL|IGNORE|REPLACE)\s+)?(?:AUTOINCREMENT\s+)?%si',
                    '',
                    $value
                );
            }

            unset($value);

            $tmp = [];

            foreach ($schema['TABLE']['OTHERS'] as $value) {
                if (\preg_match('%\bPRIMARY\s+KEY\b%si', $value)) {
                    continue;
                }

                $tmp[] = $value;
            }

            $schema['TABLE']['OTHERS'] = $tmp;
            $tmpTable                  = $this->createTmpTable($schema, $table);

            if (! \is_string($tmpTable)) {
                return false;
            }

            $tmp   = '"' . \implode('", "', \array_keys($schema['TABLE']['FIELDS'])) . '"';
            $query = "INSERT INTO \"{$tmpTable}\" ({$tmp})
                SELECT {$tmp}
                FROM \"{$table}\"";

            return $this->tmpToTable($schema, $query);
        } else {
            $this->nameCheck($index);

            return false !== $this->db->exec("DROP INDEX \"{$table}_{$index}\"");
        }
    }

    /**
     * Очищает таблицу
     */
    public function truncateTable(string $table): bool
    {
        $table = $this->tName($table);

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
