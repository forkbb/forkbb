<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core\DB;

use ForkBB\Core\DBStatement;
use PDO;
use PDOStatement;
use PDOException;

abstract class AbstractStatement extends DBStatement
{
    /**
     * Типы столбцов полученные через getColumnMeta()
     * @var array
     */
    protected $columnsType;

    /**
     * Режим выборки установленный через setFetchMode()
     * @var int
     */
    protected $fetchMode;

    /**
     * colno, class или object из setFetchMode()
     * @var mixed
     */
    protected $fetchArg;

    /**
     * constructorArgs из setFetchMode()
     * @var array
     */
    protected $ctorArgs;

    abstract public function getColumnsType(): array;
    abstract protected function convToBoolean(/* mixed */ $value): bool;

    protected function dbSetFetchMode(int $mode, ...$args): bool
    {
        $this->fetchMode = $mode;
        $this->fetchArg  = null;
        $this->ctorArgs  = null;

        switch ($mode) {
            case PDO::FETCH_CLASS:
                $this->ctorArgs = $args[1] ?? null;
            case PDO::FETCH_COLUMN:
            case PDO::FETCH_INTO:
                $this->fetchArg = $args[0];
                break;
        }

        return parent::setFetchMode($mode, ...$args);
    }

    protected function dbFetch(int $mode, int $cursorOrientation, int $cursorOffset) /* : mixed */
    {
        if (0 === $mode) {
            $mode   = $this->fetchMode ?? 0;
            $colNum = $this->fetchArg ?? 0;
        } else {
            $colNum = 0;
        }

        $data = parent::fetch(
            PDO::FETCH_COLUMN === $mode ? PDO::FETCH_NUM : $mode,
            $cursorOrientation,
            $cursorOffset
        );

        if (! \is_array($data)) {
            return $data;
        }

        $types = $this->getColumnsType();

        foreach ($data as $key => &$value) {
            if (
                isset($types[$key])
                && \is_scalar($value)
            ) {
                switch ($types[$key]) {
                    case self::INTEGER:
                        $value += 0; // If the string is not a number, then Warning/Notice
                                     // It can return not an integer, but a float.
                        break;
                    case self::BOOLEAN:
                        $value = $this->convToBoolean($value);
                        break;
                    case self::FLOAT:
                    case self::STRING:
                        break;
                    default:
                        throw new PDOException("Unknown field type: '{$types[$key]}'");
                }
            }
        }

        unset($value);

        if (PDO::FETCH_COLUMN === $mode) {
            $data = $data[$colNum];
        }

        return $data;
    }

    protected function dbFetchAll(int $mode = 0 /* PDO::FETCH_DEFAULT */, /* mixed */ ...$args): array
    {
        return parent::fetchAll($mode, ...$args);
    }
}
