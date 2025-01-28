<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core\Mail;

use InvalidArgumentException;
use RuntimeException;

class FileQueue
{
    /**
     * Директория заданий на отправку
     */
    protected string $path;

    /**
     * Ресурс для блокировки
     */
    protected $lock;

    public function __construct(string $path)
    {
        $path = \rtrim($path, '\\/');

        if (empty($path)) {
            throw new InvalidArgumentException('Mail directory unset');

        } elseif (! \is_dir($path)) {
            throw new RuntimeException("Not a directory: {$path}");

        } elseif (! \is_writable($path)) {
            throw new RuntimeException("No write access to directory: {$path}");
        }

        $this->path = $path;
    }

    /**
     * Добавляет элемент в очередь
     */
    public function push(array $data, int $priority = 5): bool
    {
        $priority = 9 - \min(\max(0, $priority), 9);

        do {
            $file = $this->path . '/job' . $priority . \str_replace('.', '', (string) \microtime(true)) . '.data';
        } while (\file_exists($file));

        return false !== \file_put_contents($file, \json_encode($data, FORK_JSON_ENCODE), \LOCK_EX);
    }

    /**
     * Выполняет существующие задания
     */
    public function execute($handler): int|false
    {
        if (false === $this->lock()) {
            return false;
        }

        $count = 0;

        foreach ($this->getIdList() as $file) {
            $data = \file_get_contents("{$this->path}/{$file}");

            if (empty($data)) {
                continue;
            }

            $data = \json_decode($data, true, 512, \JSON_THROW_ON_ERROR);

            $result = $handler($data);

            if (true === $result) {
                ++$count;

                \unlink("{$this->path}/{$file}");
            }
        }

        $this->unlock();

        return $count;
    }

    /**
     * Возвращает список файлов с заданиями
     */
    public function getIdList(): array
    {
        $result = [];

        if ($dh = \opendir($this->path)) {
            while (false !== ($file = \readdir($dh))) {
                if (
                    \preg_match('%^job.+\.data$%', $file, $matches)
                    && \is_file("{$this->path}/{$file}")
                ) {
                    $result[] = $file;
                }
            }

            \closedir($dh);
            \natsort($result);
        }

        return $result;
    }

    /**
     * Пытается получить блокировку
     */
    protected function lock(): bool
    {
        $this->lock = \fopen("{$this->path}/lock.file", 'c');

        return $this->lock && \flock($this->lock, \LOCK_EX | \LOCK_NB);
    }

    /**
     * Снимает блокировку
     */
    protected function unlock(): bool
    {
        return $this->lock && \fclose($this->lock);
    }
}
