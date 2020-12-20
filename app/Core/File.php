<?php

declare(strict_types=1);

namespace ForkBB\Core;

use ForkBB\Core\Exceptions\FileException;
use InvalidArgumentException;

class File
{
    /**
     * Текст ошибки
     * @var null|string
     */
    protected $error;

    /**
     * Путь до файла
     * @var null|string
     */
    protected $path;

    /**
     * Содержимое файла
     * @var null|string
     */
    protected $data;

    /**
     * Оригинальное имя файла без расширения
     * @var null|string
     */
    protected $name;

    /**
     * Оригинальное расширение файла
     * @var null|string
     */
    protected $ext;

    /**
     * Размер оригинального файла
     */
    protected $size;

    /**
     * Флаг автопереименования файла
     * @var bool
     */
    protected $rename  = false;

    /**
     * Флаг перезаписи файла
     * @var bool
     */
    protected $rewrite = false;

    /**
     * Паттерн для pathinfo
     * @var string
     */
    protected $pattern = '%^(?!.*?\.\.)([\w.\x5C/:-]*[\x5C/])?(\*|[\w.-]+)\.(\*|[a-z\d]+)$%i';

    public function __construct(string $path, array $options)
    {
        if (! \is_file($path)) {
            throw new FileException('File not found');
        }
        if (! \is_readable($path)) {
            throw new FileException('File can not be read');
        }

        $this->path = $path;
        $this->data = null;

        $name = null;
        $ext  = null;
        if (isset($options['basename'])) {
            if (false === ($pos = \strrpos($options['basename'], '.'))) {
                $name = $options['basename'];
            } else {
                $name = \substr($options['basename'], 0, $pos);
                $ext  = \substr($options['basename'], $pos + 1);
            }
        }

        $this->name = isset($options['filename']) && \is_string($options['filename']) ? $options['filename'] : $name;
        $this->ext  = isset($options['extension']) && \is_string($options['extension']) ? $options['extension'] : $ext;

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
     * Фильрует и переводит в латиницу(?) имя файла
     */
    protected function filterName(string $name): string
    {
        $name = \transliterator_transliterate(
            "Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();",
            $name
        );

        $name = \trim(\preg_replace('%[^\w.-]+%', '-', $name), '-');

        if (! isset($name[0])) {
            $name = (string) \time();
        }

        return $name;
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
            $matches[2] = $this->filterName($this->name);
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
            if (! @\mkdir($dirname, 0755)) {
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
        @\chmod($path, 0644);

        return true;
    }

    /**
     * Сохраняет файл по указанному шаблону пути
     */
    public function toFile(string $path, int $maxSize = null): bool
    {
        $info = $this->pathinfo($path);

        if (
            null === $info
            || ! $this->dirProc($info['dirname'])
        ) {
            return false;
        }

        if ($this->rename) {
            $old = $info['filename'];
            $i   = 1;
            while (\file_exists($info['dirname'] . $info['filename'] . '.' . $info['extension'])) {
                ++$i;
                $info['filename'] = $old . '_' . $i;
            }
        } elseif (
            ! $this->rewrite
            && \file_exists($info['dirname'] . $info['filename'] . '.' . $info['extension'])
        ) {
            $this->error = 'Such file already exists';

            return false;
        }

        $path = $info['dirname'] . $info['filename'] . '.' . $info['extension'];

        if ($this->fileProc($path)) {
            $this->size = \filesize($path);

            if (
                null !== $maxSize
                && $this->size > $maxSize
            ) {
                $this->error = 'File is larger than the allowed size';

                @\unlink($path);

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
