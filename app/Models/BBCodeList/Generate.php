<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\BBCodeList;

use ForkBB\Models\Method;
use ForkBB\Models\BBCodeList\BBCodeList;
use RuntimeException;

class Generate extends Method
{
    /**
     * Создает файл с массивом сгенерированных bbcode
     */
    public function generate(): BBCodeList
    {
        $content = "<?php\n\nuse function \\ForkBB\\{__, url};\n\nreturn [\n";
        $query   = 'SELECT bb_structure FROM ::bbcode';
        $stmt    = $this->c->DB->query($query);

        while ($row = $stmt->fetch()) {
            $content .= "    [\n"
                . $this->addArray(\json_decode($row['bb_structure'], true, 512, \JSON_THROW_ON_ERROR))
                . "    ],\n";
        }

        $content .= "];\n";

        if (false === \file_put_contents($this->model->fileCache, $content, \LOCK_EX)) {
            throw new RuntimeException('The generated bbcode file cannot be created');

        } else {
            return $this->model->invalidate();
        }
    }

    /**
     * Преобразует массив по аналогии с var_export()
     */
    protected function addArray(array $array, int $level = 0): string
    {
        $space  = \str_repeat('    ', $level + 2);
        $result = '';

        foreach ($array as $key => $value) {
            $type = \gettype($value);

            switch ($type) {
                case 'NULL':
                    $value = 'null';

                    break;
                case 'boolean':
                    $value = $value ? 'true' : 'false';

                    break;
                case 'array':
                    $value = "[\n" . $this->addArray($value, $level + 1) . "{$space}]";

                    break;
                case 'double':
                case 'integer':
                    break;
                case 'string':
                    if (
                        0 === $level
                        && (
                             'handler' === $key
                             || 'text_handler' === $key
                        )
                    ) {
                        $value = "function (\$body, \$attrs, \$parser, \$id) {\n{$value}\n{$space}}";

                    } else {
                        $value = '\'' . \addslashes($value) . '\'';
                    }

                    break;
                default:
                    throw new RuntimeException("Invalid data type ({$type})");
            }

            if (\is_string($key)) {
                $key = "'{$key}'";
            }

            $result .= "{$space}{$key} => {$value},\n";
        }

        return $result;
    }
}
