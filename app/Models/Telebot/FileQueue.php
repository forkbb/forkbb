<?php
/**
 * This file is part of the ForkBB <https://forkbb.org, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Telebot;

use ForkBB\Core\Container;
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

    public function __construct(string $path, protected Container $c)
    {
        $path = \rtrim($path, '\\/');

        if (empty($path)) {
            throw new InvalidArgumentException('Telegram directory unset');

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
    public function push(int $chatId, string $text): bool
    {
        do {
            $file = $this->path . '/job' . \str_replace('.', '', (string) \microtime(true)) . "_{$chatId}.data";
        } while (\file_exists($file));

        $data = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];

        return false !== \file_put_contents($file, \json_encode($data, FORK_JSON_ENCODE), \LOCK_EX);
    }

    /**
     * Выполняет существующие задания
     */
    public function execute(): int|false
    {
        if (false === $this->lock()) {
            return false;
        }

        $end   = FORK_START + 55; //\min(60, (int) (\ini_get('max_execution_time') ?: 30)) - 5; В CLI max_execution_time возвращает 0
        $count = 0;
        $stage = 0;
        $sleep = 0;

        do {
            if ($sleep > 0) {
                if ($sleep >= 1) {
                    \sleep((int) $sleep);

                } else {
                    \usleep((int) ($sleep * 1000000));
                }

                $sleep = 0;
            }

            switch ($stage) {
                case 0:
                    $list = $this->groupByChats($this->getIdList());

                    if (empty($list)) {
                        $sleep = 20;
                    } else {
                        $list  = \array_reverse($list, true);
                        $stage = 1;
                        $point = \time();
                    }

                    break;

                case 1:
                    if (empty($list)) {
                        $stage = 0;
                        $sleep = 20 - \time() + $point;

                    } else {
                        $files = \array_pop($list);
                        $stage = 2;
                    }

                    break;

                case 2:
                    $chatId = null;
                    $text   = '';
                    $delete = [];

                    foreach ($files as $file) {
                        $data = \file_get_contents("{$this->path}/{$file}");

                        if (empty($data)) {
                            continue;
                        }

                        $data = \json_decode($data, true, 512, \JSON_THROW_ON_ERROR);

                        if (! isset($data['chat_id'], $data['text'])) {
                            continue;

                        } elseif (null === $chatId) {
                            $chatId   = $data['chat_id'];
                            $text     = $data['text'];
                            $delete[] = $file;

                        } elseif ($chatId === $data['chat_id']) {
                            $text     .= "\n\n---\n\n" . $data['text'];
                            $delete[]  = $file;
                        }
                    }

                    $stage = null === $chatId ? 1 : 3;

                    break;

                case 3:
                    $result = $this->c->telebot->sendMessage($chatId, $text);

                    if (true === ($result['json']['ok'] ?? null)) {
                        ++$count;

                        foreach ($delete as $file) {
                            \unlink("{$this->path}/{$file}");
                        }

                        $stage = 1;
                        $sleep = 0.035;

                    } elseif (429 === $result['httpCode']) {
                        $sleep = $result['json']['parameters']['retry_after'] ?? 5;

                    } elseif ($result['httpCode'] > 500) {
                        $sleep = 10;

                    } else {
                        $stage = 1;
                        $sleep = 0.035;
                    }

                    break;
            }

        } while (\microtime(true) < $end - $sleep);

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
                    \preg_match('%^job[^_]+_([^.]+)\.data$%', $file, $matches)
                    && \is_file("{$this->path}/{$file}")
                ) {
                    $result[$file] = $matches[1];
                }
            }

            \closedir($dh);
            \ksort($result, \SORT_NATURAL);
        }

        return $result;
    }

    /**
     * Группирует имена файлов по номерам чатов
     */
    protected function groupByChats(array $files): array
    {
        $result = [];

        foreach ($files as $file => $id) {
            $result[$id] ??= [];
            $result[$id][] = $file;
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
