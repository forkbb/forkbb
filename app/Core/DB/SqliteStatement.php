<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core\DB;

use ForkBB\Core\DB\AbstractStatement;
use PDO;

class SqliteStatement extends AbstractStatement
{
    /**
     * https://github.com/php/php-src/blob/master/ext/pdo_sqlite/sqlite_statement.c
     *
     * SQLite:
     *  native_type:
     *   null    - для значения NULL, а не типа столбца
     *   integer - это INTEGER, NUMERIC(?), BOOLEAN // BOOLEAN тут как-то не к месту, его бы в отдельный тип
     *   string  - это TEXT
     *   double  - это REAL, NUMERIC(?) // NUMERIC может быть и double, и integer
     *  sqlite:decl_type:
     *   INTEGER
     *   TEXT
     *   REAL
     *   NUMERIC
     *   BOOLEAN
     *   ... (это те типы, которые прописаны в CREATE TABLE и полученные после перекодировки из {driver}::bTypeRepl)
     */

    protected array $nativeTypeRepl = [
        'integer' => self::INTEGER,
        'double'  => self::FLOAT,
    ];

    public function getColumnsType(): array
    {
        if (isset($this->columnsType)) {
            return $this->columnsType;
        }

        $this->columnsType = [];

        $count  = $this->columnCount();
        $i      = 0;
//        $dbType = $this->db->getType();

        for ($i = 0; $i < $count; $i++) {
            $meta     = $this->getColumnMeta($i);
            $type     = null;
//            $declType = $meta[$dbType . ':decl_type'] ?? null;
            $declType = $meta['sqlite:decl_type'] ?? null;

            if (null === $declType) {
                $type = $this->nativeTypeRepl[$meta['native_type']] ?? null;

            } elseif (\preg_match('%INT%i', $declType)) {
                $type = self::INTEGER;

            } elseif (\preg_match('%BOOL%i', $declType)) {
                $type = self::BOOLEAN;
//            } elseif (\preg_match('%REAL|FLOA|DOUB|NUMERIC|DECIMAL%i', $declType)) {
//                $type = self::FLOAT;
            }

            if ($type) {
                $this->columnsType[$i] = $type;

                if (isset($meta['name'])) { // ????? проверка на тип содержимого? только строки, не числа?
                    $this->columnsType[$meta['name']] = $type;
                }
            }
        }

        return $this->columnsType;
    }

    protected function convToBoolean(mixed $value): bool
    {
        return (bool) $value;
    }
}
