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
     * Режим выборки установленный через setFetchMode()/fetchAll()
     * @var int
     */
    protected $fetchMode;

    /**
     * colno, class или object из setFetchMode()/fetchAll()
     * @var mixed
     */
    protected $fetchArg;

    /**
     * constructorArgs из setFetchMode()/fetchAll()
     * @var array
     */
    protected $ctorArgs;

    /**
     * Флаг успешного завершения fetch() для PDO::FETCH_COLUMN
     * @var bool
     */
    protected $okFetchColumn;

    abstract public function getColumnsType(): array;
    abstract protected function convToBoolean(/* mixed */ $value): bool;

    protected function setFetchVars(int $mode, ...$args): void
    {
        $this->fetchMode = $mode;
        $this->fetchArg  = null;
        $this->ctorArgs  = null;

        switch ($mode) {
            case PDO::FETCH_CLASS:
                $this->ctorArgs = $args[1] ?? null;
            case PDO::FETCH_INTO:
                $this->fetchArg = $args[0];
                break;
            case PDO::FETCH_COLUMN:
                $this->fetchArg = $args[0] ?? 0;
                break;
        }
    }

    protected function dbSetFetchMode(int $mode, ...$args): bool
    {
        $this->setFetchVars($mode, ...$args);

        return parent::setFetchMode($mode, ...$args);
    }

    protected function dbFetch(int $mode = 0, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0) /* : mixed */
    {
        $this->okFetchColumn = false;

        if (0 === $mode) {
            $mode   = $this->fetchMode ?? 0;
            $colNum = $this->fetchArg ?? 0;
        } else {
            $colNum = 0;
        }

        $data = parent::fetch(
            PDO::FETCH_COLUMN === $mode ? PDO::FETCH_NUM : $mode,
            $orientation,
            $offset
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
            $this->okFetchColumn = true;
            $data                = $data[$colNum];
        }

        return $data;
    }

    protected function dbFetchAll(int $mode, ...$args): array
    {
        if (0 !== $mode) {
            $this->setFetchVars($mode, ...$args);
        }

        $result = [];

        switch ($this->fetchMode) {
            case 0: /* PDO::FETCH_DEFAULT */
            case PDO::FETCH_BOTH:
            case PDO::FETCH_NUM:
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_COLUMN:
                while (false !== ($data = $this->dbFetch()) || $this->okFetchColumn) {
                    $result[] = $data;
                }

                break;
            case PDO::FETCH_KEY_PAIR:
                if (2 !== $this->columnCount()) {
                    throw new PDOException('General error: PDO::FETCH_KEY_PAIR fetch mode requires the result set to contain exactly 2 columns');
                }

                while (false !== ($data = $this->dbFetch(PDO::FETCH_NUM))) {
                    $result[$data[0]] = $data[1];
                }

                break;
            case PDO::FETCH_UNIQUE:
            case PDO::FETCH_UNIQUE | PDO::FETCH_BOTH:
            case PDO::FETCH_UNIQUE | PDO::FETCH_NUM:
            case PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC:
                $this->fetchMode ^= PDO::FETCH_UNIQUE;

                while (false !== ($data = $this->dbFetch())) {
                    $key          = \array_shift($data);
                    $result[$key] = $data;
                }

                break;
            case PDO::FETCH_GROUP:
            case PDO::FETCH_GROUP | PDO::FETCH_BOTH:
            case PDO::FETCH_GROUP | PDO::FETCH_NUM:
            case PDO::FETCH_GROUP | PDO::FETCH_ASSOC:
                $this->fetchMode ^= PDO::FETCH_GROUP;

                while (false !== ($data = $this->dbFetch())) {
                    $key = \array_shift($data);

                    if (PDO::FETCH_BOTH === $this->fetchMode) {
                        \array_shift($data);;
                    }

                    if (! isset($result[$key])) {
                        $result[$key] = [];
                    }

                    $result[$key][] = $data;
                }

                    break;
            default:
                throw new PDOException('AbstractStatement class does not support this type for fetchAll(): ' . $this->fetchMode);

                return parent::fetchAll($mode, ...$args);
        }

        return $result;

    }
}
