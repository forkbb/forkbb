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
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;
use DateTimeZone;
use DateTime;
use RuntimeException;
use Throwable;

class Log implements LoggerInterface
{
    const JSON_OPTIONS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR;

    protected string $path;
    protected string $lineFormat;
    protected string $timeFormat;
    protected $resource;
    protected string $hidePath;

    public function __construct(array $config, protected Container $c)
    {
        $this->path       = $config['path']       ?? __DIR__ . '/../log/{Y-m-d}.log';
        $this->lineFormat = $config['lineFormat'] ?? "%datetime% [%level_name%] %message%\t%context%\n";
        $this->timeFormat = $config['timeFormat'] ?? 'Y-m-d H:i:s';
        $this->hidePath   = \realpath(__DIR__ . '/../../');
    }

    public function __destruct()
    {
        if (\is_resource($this->resource)) {
            \fclose($this->resource);
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, $message, array $context = [])
    {
        if (
            \is_object($message)
            && \method_exists($message, '__toString')
        ) {
            $message = (string) $message;

        } elseif (! \is_string($message)) {
            throw new InvalidArgumentException('Expected string in message');
        }

        if (! \is_string($level)) {
            throw new InvalidArgumentException('Expected string in level');
        }

        switch ($level) {
            case LogLevel::EMERGENCY:
            case LogLevel::ALERT:
            case LogLevel::CRITICAL:
            case LogLevel::ERROR:
            case LogLevel::WARNING:
            case LogLevel::NOTICE:
            case LogLevel::INFO:
            case LogLevel::DEBUG:
                break;
            default:
                throw new InvalidArgumentException('Invalid level value');
        }

        $context = $this->contextExp($level, $context);
        $line    = $this->generateLine(
            $level,
            $this->c->Secury->replInvalidChars($message),
            $context
        );

        if (! \is_resource($this->resource)) {
            $this->initResource();
        }

        \flock($this->resource, \LOCK_EX);
        \fwrite($this->resource, $line);
        \flock($this->resource, \LOCK_UN);
    }

    protected function contextExp(string $level, array $context): array
    {
        $ext     = $context['headers'] ?? null;
        $headers = [
            'REMOTE_ADDR'     => $_SERVER['REMOTE_ADDR'] ?? null,
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        if (null === $ext) {
            switch ($level) {
                case LogLevel::EMERGENCY:
                case LogLevel::ALERT:
                case LogLevel::CRITICAL:
                case LogLevel::ERROR:
                case LogLevel::WARNING:
                    $ext = true;
                    break;
            }
        }

        if (true === $ext) {
            foreach ($_SERVER as $key => $value) {
                if (
                    'HTTP_' === \substr($key, 0, 5)
                    && 'HTTP_USER_AGENT' !== $key
                    && 'HTTP_COOKIE' !== $key
                ) {
                    $headers[$key] = $value;
                }
            }
        }

        $context['headers'] = $headers;

        return $context;
    }

    protected function initResource(): void
    {
        $dt   = new DateTime('now', new DateTimeZone('UTC'));
        $path = \preg_replace_callback(
            '%{([^{}]+)}%',
            function ($matches) use ($dt) {
                $result = $dt->format($matches[1]);

                return $result ?: 'bad_format';
            },
            $this->path
        );

        if (! \is_writable($path)) {
            $dir = \pathinfo($path, \PATHINFO_DIRNAME);

            if (
                ! \is_dir($dir)
                && ! \mkdir($dir, 0755, true)
            ) {
                throw new RuntimeException("Unable to create '{$dir}' directory");
            }

            if (! \chmod($dir, 0755)) {
                throw new RuntimeException("Error changing the access mode to '{$dir}' directory");
            }

            if (
                \is_file($path)
                && ! \chmod($dir, 0755)
            ) {
                throw new RuntimeException("Error changing the access mode to '{$path}' file");
            }
        }

        $this->resource = \fopen($path, 'a');

        if (! \is_resource($this->resource)) {
            throw new RuntimeException("Could not get access to '{$path}' resource");
        }
    }

    protected function generateLine(string $level, string $message, array $context): string
    {
        if (
            false !== \strpos($message, '{')
            && false !== \strpos($message, '}')
        ) {
            $message = $this->interpolate($message, $context);
        }

        if (
            isset($context['exception'])
            && $context['exception'] instanceof Throwable
        ) {
            $context['exception'] = \str_replace($this->hidePath, '...', (string) $context['exception']);
        }

        $dt     = new DateTime('now', new DateTimeZone('UTC'));
        $result = [
            '%datetime%'   => $dt->format($this->timeFormat),
            '%level_name%' => $level,
            '%message%'    => \addcslashes($message, "\0..\37\\"),
            '%context%'    => \json_encode($context, self::JSON_OPTIONS),
        ];

        return \strtr($this->lineFormat, $result);
    }

    /**
     * Interpolates context values into the message placeholders.
    */
    protected function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            // check that the value can be cast to string
            if (
                ! \is_array($val)
                && (
                    ! \is_object($val)
                    || \method_exists($val, '__toString')
                )
            ) {
                $replace['{' . $key . '}'] = \str_replace($this->hidePath, '...', (string) $val);
            }
        }

        // interpolate replacement values into the message and return
        return \strtr($message, $replace);
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
}
