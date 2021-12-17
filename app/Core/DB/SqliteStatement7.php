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
 * For PHP 7
 */
class SqliteStatement7 extends AbstractSqliteStatement
{
    public function fetch($mode = null, $orientation = null, $offset = null)
    {
        $mode        = $mode ?? 0;
        $orientation = $orientation ?? PDO::FETCH_ORI_NEXT;
        $offset      = $offset ?? 0;

        return $this->dbFetch($mode, $orientation, $offset);
    }

    public function fetchAll($mode = null, $fetchArg = null, $ctorArgs = null)
    {
        $mode = $mode ?? 0;
        $args = $this->returnArgs($fetchArg, $ctorArgs);

        return $this->dbFetchAll($mode, ...$args);
    }

    public function setFetchMode($mode, $fetchArg = null, $ctorArgs = null): bool
    {
        $args = $this->returnArgs($fetchArg, $ctorArgs);

        return $this->dbSetFetchMode($mode, ...$args);
    }

    protected function returnArgs($fetchArg, $ctorArgs): array
    {
        $args = [];

        if (isset($fetchArg)) {
            $args[] = $fetchArg;

            if (isset($ctorArgs)) {
                $args[] = $ctorArgs;
            }
        }

        return $args;
    }
}
