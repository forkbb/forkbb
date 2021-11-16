<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Container;
use Psr\Log\NullLogger;
use Throwable;

class ErrorHandler
{
    /**
     * Контейнер
     * @var Container
     */
    protected $c;

    /**
     * Уровень буфера вывода на котором работает обработчик
     * @var int
     */
    protected $obLevel;

    /**
     * Описание ошибки
     * @var array
     */
    protected $error;

    /**
     * Скрываемая часть пути до файла
     * @var string
     */
    protected $hidePath;

    /**
     * Список ошибок
     * @var array
     */
    protected $type = [
        0                    => ['OTHER_ERROR',         'error'],
        \E_ERROR             => ['E_ERROR',             'error'],
        \E_WARNING           => ['E_WARNING',           'warning'],
        \E_PARSE             => ['E_PARSE',             'critical'],
        \E_NOTICE            => ['E_NOTICE',            'notice'],
        \E_CORE_ERROR        => ['E_CORE_ERROR',        'error'],
        \E_CORE_WARNING      => ['E_CORE_WARNING',      'warning'],
        \E_COMPILE_ERROR     => ['E_COMPILE_ERROR',     'error'],
        \E_COMPILE_WARNING   => ['E_COMPILE_WARNING',   'warning'],
        \E_USER_ERROR        => ['E_USER_ERROR',        'error'],
        \E_USER_WARNING      => ['E_USER_WARNING',      'warning'],
        \E_USER_NOTICE       => ['E_USER_NOTICE',       'notice'],
        \E_STRICT            => ['E_STRICT',            'error'],
        \E_RECOVERABLE_ERROR => ['E_RECOVERABLE_ERROR', 'error'],
        \E_DEPRECATED        => ['E_DEPRECATED',        'warning'],
        \E_USER_DEPRECATED   => ['E_USER_DEPRECATED',   'warning'],
    ];

    public function __construct()
    {
        $this->hidePath = \realpath(__DIR__ . '/../../');

        \set_error_handler([$this, 'errorHandler'], \E_ALL);
        \set_exception_handler([$this, 'exceptionHandler']);
        \register_shutdown_function([$this, 'shutdownHandler']);

        \ob_start();
        $this->obLevel = \ob_get_level();
    }

    public function __destruct()
    {
        \restore_error_handler();
        \restore_exception_handler();

        //????
    }

    public function setContainer(Container $c): void
    {
        $this->c = $c;
    }

    /**
     * Обрабатыет перехватываемые ошибки
     */
    public function errorHandler(int $type, string $message, string $file, int $line): bool
    {
        if ($type & \error_reporting()) {
            $this->error = [
                'type'    => $type,
                'message' => $message,
                'file'    => $file,
                'line'    => $line,
                'trace'   => \debug_backtrace(0),
            ];

            $this->log($this->error);

            exit(1);
        }

        return true;
    }

    /**
     * Обрабатывает не перехваченные исключения
     */
    public function exceptionHandler(Throwable $e): void
    {
        $this->error = [
            'type'      => 0,
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTrace(),
            'exception' => $e,
        ];

        $this->log($this->error);
    }

    /**
     * Окончательно обрабатывает ошибки (в том числе фатальные) и исключения
     */
    public function shutdownHandler(): void
    {
        $show = false;

        if (isset($this->error['type'])) {
            $show = true;
        } elseif (
            \is_array($this->error = \error_get_last())
            && $this->error['type'] & \error_reporting()
        ) {
            $show = true;

            $this->log($this->error);
        }

        while (\ob_get_level() > $this->obLevel) {
            \ob_end_clean();
        }

        if (\ob_get_level() === $this->obLevel) {
            if ($show) {
                \ob_end_clean();

                $this->show($this->error);
            } else {
                \ob_end_flush();
            }
        }
    }

    /**
     * Отправляет сообщение в лог
     */
    protected function log(array $error): void
    {
        $useErrLog = true;

        try {
            if (! $this->c->Log instanceof NullLogger) {
                $context = [];
                $method  = $this->type[$error['type']][1] ?? $this->type[0][1];

                if (isset($error['exception'])) {
                    $context['exception'] = $error['exception'];
                }
                $context['headers'] = false;

                $this->c->Log->{$method}($this->message($error), $context);

                $useErrLog = false;
            }
        } catch (Throwable $e) {
            \error_log($this->message([
                'type'      => 0,
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTrace(),
                'exception' => $e,
            ], true));
        }

        if ($useErrLog) {
            \error_log($this->message($error, true));
        }
    }

    /**
     * Выводит сообщение об ошибке
     *
     * @param array $error
     */
    protected function show(array $error): void
    {
        \header('HTTP/1.1 500 Internal Server Error');
        \header('Content-Type: text/html; charset=utf-8');

        echo <<<'EOT'
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 Internal Server Error</title>
</head>
<body>

EOT;

        if (1 == \ini_get('display_errors')) {
            echo '<p>' . $this->e($this->message($error)) . '</p>';

            if (
                isset($error['trace'])
                && \is_array($error['trace'])
            ) {
                echo '<div><p>Trace:</p><ol>';

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

                    $line = $this->e(\str_replace($this->hidePath, '...', $line));
                    echo "<li>{$line}</li>";
                }

                echo '</ol></div>';
            }
        } else {
            echo '<p>Server is tired :(</p>';
        }

        echo <<<'EOT'

</body>
</html>

EOT;

    }

    /**
     * Формирует сообщение
     */
    protected function message(array $error, bool $useException = false): string
    {
        $type = $this->type[$error['type']][0] ?? $this->type[0][0];

        if (
            $useException
            && isset($error['exception'])
            && $error['exception'] instanceof Throwable
        ) {
            $result = "PHP {$type}: {$error['exception']}";
        } else {
            $result = "PHP {$type}: {$error['message']} in {$error['file']}:[{$error['line']}]";
        }

        return \preg_replace('%[\x00-\x1F]%', ' ', \str_replace($this->hidePath, '...', $result));
    }

    /**
     * Экранирует спецсимволов HTML-сущностями
     */
    protected function e(string $arg): string
    {
        return \htmlspecialchars($arg, \ENT_HTML5 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
