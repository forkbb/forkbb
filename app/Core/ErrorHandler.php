<?php

namespace ForkBB\Core;

use Throwable;

class ErrorHandler
{
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
     * Флаг отправки сообщения в лог
     * @var bool
     */
    protected $logged = false;

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
        0                    => 'OTHER_ERROR',
        \E_ERROR             => 'E_ERROR',
        \E_WARNING           => 'E_WARNING',
        \E_PARSE             => 'E_PARSE',
        \E_NOTICE            => 'E_NOTICE',
        \E_CORE_ERROR        => 'E_CORE_ERROR',
        \E_CORE_WARNING      => 'E_CORE_WARNING',
        \E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        \E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        \E_USER_ERROR        => 'E_USER_ERROR',
        \E_USER_WARNING      => 'E_USER_WARNING',
        \E_USER_NOTICE       => 'E_USER_NOTICE',
        \E_STRICT            => 'E_STRICT',
        \E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        \E_DEPRECATED        => 'E_DEPRECATED',
        \E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    ];

    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->hidePath = \realpath(__DIR__ . '/../../');

        \set_error_handler([$this, 'errorHandler']);
        \set_exception_handler([$this, 'exceptionHandler']);
        \register_shutdown_function([$this, 'shutdownHandler']);

        \ob_start();
        $this->obLevel = \ob_get_level();
    }

    /**
     * Деструктор
     */
    public function __destruct()
    {
        \restore_error_handler();
        \restore_exception_handler();

        //????
    }

    /**
     * Обрабатыет перехватываемые ошибки
     *
     * @param int    $type
     * @param string $message
     * @param string $file
     * @param string $line
     *
     * @return bool
     */
    public function errorHandler(int $type, string $message, string $file, string $line): bool
    {
        $error = [
            'type'    => $type,
            'message' => $message,
            'file'    => $file,
            'line'    => $line,
            'trace'   => \debug_backtrace(0),
        ];
        $this->log($error);

        if ($type & \error_reporting()) {
            $this->error = $error;
            exit(1);
        }

        $this->logged = false;
        return true;
    }

    /**
     * Обрабатывает не перехваченные исключения
     *
     * @param Exception|Throwable $e
     */
    public function exceptionHandler(Throwable $e): void
    {
        $this->error = [
            'type'    => 0, //????
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTrace(),
        ];
    }

    /**
     * Окончательно обрабатывает ошибки (в том числе фатальные) и исключения
     */
    public function shutdownHandler(): void
    {
        if (isset($this->error['type'])) {
            $show = true;
        } else {
            $show = false;
            $this->error = \error_get_last();

            if (isset($this->error['type'])) {
                switch ($this->error['type']) {
                    case \E_ERROR:
                    case \E_PARSE:
                    case \E_CORE_ERROR:
                    case \E_CORE_WARNING:
                    case \E_COMPILE_ERROR:
                    case \E_COMPILE_WARNING:
                        $show = true;
                        break;
                }
            }
        }

        if (isset($this->error['type']) && ! $this->logged) {
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
     *
     * @param array $error
     */
    protected function log(array $error): void
    {
        $this->logged = true;
        $message = \preg_replace('%[\x00-\x1F]%', ' ', $this->message($error));

        \error_log($message);
    }

    /**
     * Выводит сообщение об ошибке
     *
     * @param array $error
     */
    protected function show(array $error): void
    {
        \header('HTTP/1.1 500 Internal Server Error');

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

            if (isset($error['trace']) && \is_array($error['trace'])) {
                echo '<div><p>Trace:</p><ol>';

                foreach ($error['trace'] as $cur) {
                    if (isset($cur['file'], $cur['line'], $error['file'], $error['line'])
                        && $error['line'] === $cur['line']
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

                    if (! empty($cur['args']) && \is_array($cur['args'])) {
                        $comma = '';

                        foreach($cur['args'] as $arg) {
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
            echo '<p>Oops</p>';
        }

        echo <<<'EOT'

</body>
</html>

EOT;

    }

    /**
     * Формирует сообщение
     *
     * @param array $error
     *
     * @return string
     */
    protected function message(array $error): string
    {
        $type = $this->type[$error['type']] ?? $this->type[0];
        $file = \str_replace($this->hidePath, '...', $error['file']);
        return "PHP {$type}: \"{$error['message']}\" in {$file}:[{$error['line']}]";
    }

    /**
     * Экранирует спецсимволов HTML-сущностями
     *
     * @param  string $arg
     *
     * @return string
     */
    protected function e(string $arg): string
    {
        return \htmlspecialchars($arg, \ENT_HTML5 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
