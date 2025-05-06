<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core\Image;

use ForkBB\Core\Files;
use ForkBB\Core\Exceptions\FileException;

class DefaultDriver
{
    const DEFAULT = true;

    protected bool $ready = false;

    public function __construct(protected Files $files)
    {
        $this->ready = true;
    }

    public function ready(): bool
    {
        return $this->ready;
    }

    public function readFromStr(string $data): mixed
    {
        return false;
    }

    public function readFromPath(string $path): mixed
    {
        return false;
    }

    public function writeToPath(mixed $image, string $path, int $quality): ?bool
    {
        return null;
    }

    public function resize(mixed $image, int $maxW, int $maxH): mixed
    {
        return $image;
    }

    public function width(mixed $image): int
    {
        return 0;
    }

    public function height(mixed $image): int
    {
        return 0;
    }

    public function destroy(mixed $image): void
    {
    }
}
