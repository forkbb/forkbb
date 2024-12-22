<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Files;
use ForkBB\Core\Exceptions\FileException;
use InvalidArgumentException;

class File
{
    /**
     * Текст ошибки
     */
    protected ?string $error = null;

    /**
     * Содержимое файла
     */
    protected ?string $data;

    /**
     * Размер оригинального файла
     */
    protected int|false $size;

    /**
     * Флаг автопереименования файла
     */
    protected bool $rename = false;

    /**
     * Флаг перезаписи файла
     */
    protected bool $rewrite = false;

    /**
     * Паттерн для pathinfo
     */
    protected string $pattern = '%^(?!.*?\.\.)([\w.\x5C/:-]*[\x5C/])?(\*|[\w.-]+)\.(\*|[a-z\d]+)$%iD';

    public function __construct(protected string $path, protected string $name, protected string $ext, protected Files $files)
    {
        if ($files->isBadPath($path)) {
            throw new FileException('Bad path to file');

        } elseif (! \is_file($path)) {
            throw new FileException('File not found');

        } elseif (! \is_readable($path)) {
            throw new FileException('File can not be read');
        }

        $this->data = null;
        $this->size = \is_string($this->data) ? \strlen($this->data) : \filesize($path);

        if (! $this->size) {
            throw new FileException('File size is undefined');
        }
    }

    /**
     * Возвращает текст ошибки
     */
    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * Возвращает информацию о пути к сохраняемому файлу с учетом подстановок
     */
    protected function pathinfo(string $path): ?array
    {
        if (! \preg_match($this->pattern, $path, $matches)) {
            $this->error = 'The path/name format is broken';

            return null;
        }

        if ('*' === $matches[2]) {
            $matches[2] = $this->files->filterName($this->name);
        }

        if ('*' === $matches[3]) {
            $matches[3] = $this->ext;

        } elseif (
            '(' === $matches[3][0]
            && ')' === $matches[3][-1]
        ) {
            $matches[3] = \explode('|', \substr($matches[3], 1, -1));

            if (1 === \count($matches[3])) {
                $matches[3] = \array_pop($matches[3]);
            }
        }

        return [
            'dirname'   => $matches[1],
            'filename'  => $matches[2],
            'extension' => $matches[3],
        ];
    }

    /**
     * Устанавливает флаг автопереименования файла
     */
    public function rename(bool $rename): File
    {
        $this->rename = $rename;

        return $this;
    }

    /**
     * Устанавливает флаг перезаписи файла
     */
    public function rewrite(bool $rewrite): File
    {
        $this->rewrite = $rewrite;

        return $this;
    }

    /**
     * Создает/проверяет на запись директорию
     */
    protected function dirProc(string $dirname): bool
    {
        if (! \is_dir($dirname)) {
            if (! \mkdir($dirname, 0755, true)) {
                $this->error = 'Can not create directory';

                return false;
            }
        }

        if (! \is_writable($dirname)) {
            $this->error = 'No write access for directory';

            return false;
        }

        return true;
    }

    /**
     * Создает/устанавливает права на файл
     */
    protected function fileProc(string $path): bool
    {
        if (\is_string($this->data)) {
            if (! \file_put_contents($this->path, $path)) {
                $this->error = 'Error writing file';

                return false;
            }

        } else {
            if (! \copy($this->path, $path)) {
                $this->error = 'Error copying file';

                return false;
            }
        }

        \chmod($path, 0644);

        return true;
    }

    /**
     * Сохраняет файл по указанному шаблону пути
     */
    public function toFile(string $path, ?int $maxSize = null): bool
    {
        $info = $this->pathinfo($path);

        if (empty($info)) {
            return false;
        }

        if ($this->files->isBadPath($info['dirname'])) {
            $this->error = 'Bad path to file';

            return false;
        }

        if (
            ! $this->dirProc($info['dirname'])
        ) {
            return false;
        }

        $name = $info['filename'];
        $i    = 1;

        while (true) {
            $path = $info['dirname'] . $info['filename'] . '.' . $info['extension'];

            if ($this->files->isBadPath($path)) {
                $this->error = 'Bad path to file';

                return false;
            }

            if (\file_exists($path)) {
                if ($this->rename) {
                    ++$i;
                    $info['filename'] = $name . '_' . $i;

                    continue;

                } elseif (! $this->rewrite) {
                    $this->error = 'Such file already exists';

                    return false;
                }
            }

            break;
        }

        if ($this->fileProc($path)) {
            $this->size = \filesize($path);

            if (
                null !== $maxSize
                && $this->size > $maxSize
            ) {
                $this->error = 'File is larger than the allowed size';

                \unlink($path);

                return false;
            }

            $this->path = $path;
            $this->name = $info['filename'];
            $this->ext  = $info['extension'];

            return true;

        } else {
            return false;
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function ext(): string
    {
        return $this->ext;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function path(): string
    {
        return $this->path;
    }
}
