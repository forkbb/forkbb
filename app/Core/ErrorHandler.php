<?php

namespace ForkBB\Core;

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
        \set_error_handler([$this, 'errorHandler']);
        \set_exception_handler([$this, 'exceptionHandler']);
        \register_shutdown_function([$this, 'shutdownHandler']);

        \ob_start();
        $this->obLevel = \ob_get_level();
        $this->hidePath = \realpath(__DIR__ . '/../../');
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
    public function errorHandler($type, $message, $file, $line)
    {
        $error = [
            'type'    => $type,
            'message' => $message,
            'file'    => $file,
            'line'    => $line,
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
    public function exceptionHandler($e)
    {
        $this->error = [
            'type'    => 0, //????
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ];
    }

    /**
     * Окончательно обрабатывает ошибки (в том числе фатальные) и исключения
     */
    public function shutdownHandler()
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
    protected function log(array $error)
    {
        $this->logged = true;
        $type = isset($this->type[$error['type']]) ? $this->type[$error['type']] : $this->type[0];
        $message = "PHP {$type}: \"{$error['message']}\" in {$error['file']}:[{$error['line']}]";
        $message = \preg_replace('%[\x00-\x1F]%', ' ', $message);

        \error_log($message);
    }

    /**
     * Выводит сообщение об ошибке
     *
     * @param array $error
     */
    protected function show(array $error)
    {
        \header('HTTP/1.1 500 Internal Server Error');

        if (1 == \ini_get('display_errors')) {
            $type = isset($this->type[$error['type']]) ? $this->type[$error['type']] : $this->type[0];
            $file = \str_replace($this->hidePath, '...', $error['file']);

            echo "PHP {$type}: \"{$error['message']}\" in {$file}:[{$error['line']}]";
        } else {
            echo 'Oops';
        }
    }
}
