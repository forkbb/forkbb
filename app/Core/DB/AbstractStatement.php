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
use RuntimeException;
use ReturnTypeWillChange;

abstract class AbstractStatement extends DBStatement
{
    const BOOLEAN = 'b';
    const FLOAT   = 'f';
    const INTEGER = 'i';
    const STRING  = 's';

    /**
     * Типы столбцов полученные через getColumnMeta()
     * @var array
     */
    protected $columnsType;

    abstract public function getColumnsType(): array;
    abstract protected function convToBoolean(/* mixed */ $value): bool;

    #[ReturnTypeWillChange]
    public function fetch(/* int */ $mode = 0 /* PDO::FETCH_DEFAULT */, /* int */ $cursorOrientation = PDO::FETCH_ORI_NEXT, /* int */ $cursorOffset = 0) /* : mixed */
    {
        $data = parent::fetch($mode, $cursorOrientation, $cursorOffset);

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
                        throw new RuntimeException("Unknown field type: '{$types[$key]}'");
                }
            }
        }

        unset($value);

        return $data;
    }
}
