<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core\DB;

use ForkBB\Core\DB\AbstractSqliteStatement;
use PDO;

/**
 * For PHP 8
 */
class SqliteStatement extends AbstractSqliteStatement
{
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed
    {
        return $this->dbFetch($mode, $orientation, $offset);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        return $this->dbFetchAll($mode, ...$args);
    }

    public function setFetchMode(int $mode, ...$args): bool
    {
        return $this->dbSetFetchMode($mode, ...$args);
    }
}
