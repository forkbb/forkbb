<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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

    /**
     * @var bool
     */
    protected $ready;

    /**
     * @var Files
     */
    protected $files;

    public function __construct(Files $files)
    {
        $this->ready = true;
        $this->files = $files;
    }

    public function ready(): bool
    {
        return $this->ready;
    }

    public function readFromStr(string $data) /* : mixed|false */
    {
        return false;
    }

    public function readFromPath(string $path) /* : mixed|false */
    {
        return false;
    }

    public function writeToPath(/* mixed */ $image, string $path, int $quality): ?bool
    {
        return null;
    }

    public function resize(/* mixed */ $image, int $maxW, int $maxH) /* : mixed */
    {
        return $image;
    }

    public function destroy(/* mixed */ $image): void
    {
    }
}
