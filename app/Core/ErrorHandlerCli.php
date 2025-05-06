<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\ErrorHandler;

class ErrorHandlerCli extends ErrorHandler
{
    /**
     * Выводит сообщение об ошибке
     *
     * @param array $error
     */
    protected function show(array $error): void
    {
        echo '---' . \PHP_EOL;
        echo $this->message($error) . \PHP_EOL;

        if (
            isset($error['trace'])
            && \is_array($error['trace'])
        ) {
            echo 'Trace:' . \PHP_EOL;

            foreach ($error['trace'] as $cur) {
                if (
                    isset($cur['file'], $cur['line'], $error['file'], $error['line'])
                    && (int) $error['line'] === (int) $cur['line']
                    && $error['file'] === $cur['file']
                ) {
                    continue;
                }

                $line = $cur['file'] ?? '-';
                $line .= '(' . ($cur['line'] ?? '-') . '): ';

                if (isset($cur['class'])) {
                    $line .= $cur['class'] . $cur['type'];
                }

                $line .= ($cur['function'] ?? 'unknown') . '(';

                if (
                    ! empty($cur['args'])
                    && \is_array($cur['args'])
                ) {
                    $comma = '';

                    foreach ($cur['args'] as $arg) {
                        $type = \gettype($arg);

                        switch ($type) {
                            case 'boolean':
                                $type = $arg ? 'true' : 'false';

                                break;
                            case 'array':
                                $type .= '(' . \count($arg) . ')';

                                break;
                            case 'resource':
                                $type = \get_resource_type($arg);

                                break;
                            case 'object':
                                $type .= '{' . \get_class($arg) . '}';

                                break;
                        }

                        $line .= $comma . $type;
                        $comma = ', ';
                    }
                }
                $line .= ')';
                $line  = \str_replace($this->hidePath, '...', $line);

                echo $line . \PHP_EOL;
            }
        }

        echo '---' . \PHP_EOL;
    }
}
