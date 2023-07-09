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
use ForkBB\Core\Image\DefaultDriver;
use ForkBB\Core\Exceptions\FileException;

class GDDriver extends DefaultDriver
{
    const DEFAULT = false;

    public function __construct(Files $files)
    {
        parent::__construct($files);

        $this->ready = \extension_loaded('gd') && \function_exists('\\imagecreatetruecolor');
    }

    public function readFromStr(string $data): mixed
    {
        if ($this->isBadData(\substr($data, 0, 64))) {
            return false;
        } else {
            return $this->tuning($this->ready ? \imagecreatefromstring($data) : false);
        }
    }

    public function readFromPath(string $path): mixed
    {
        if (
            ! $this->ready
            || $this->files->isBadPath($path)
            || $this->isBadData(\file_get_contents($path, false, null, 0, 64))
        ) {
            return false;
        } else {
            return $this->tuning(\imagecreatefromstring(\file_get_contents($path)));
        }
    }

    protected function isBadData(string $data): bool
    {
        if (
            8 === \strpos($data, 'WEBP')
            && (
                \strpos($data, 'ANIM')
                || \strpos($data, 'ANMF')
            )
        ) {
            return true;
        }

        return false;
    }

    protected function tuning(mixed $image): mixed
    {
        if (
            false !== $image
            && (
                false === \imagealphablending($image, false)
                || false === \imagesavealpha($image, true)
            )
        ) {
            throw new FileException('Failed to adjust image');
        } else {
            return $image;
        }
    }

    public function writeToPath(mixed $image, string $path, int $quality): ?bool
    {
        $args = [$image, $path];
        $type = \pathinfo($path, \PATHINFO_EXTENSION);

        switch ($type) {
            case 'gif':
                break;
            case 'png':
                $args[] = 9; //(int) \floor((100 - $quality) / 11);
                break;
            case 'jpg':
                $type   = 'jpeg';
//                $args[] = $quality;
//                break;
            case 'webp':
//                if (defined('\\IMG_WEBP_LOSSLESS')) {
//                    $quality = \IMG_WEBP_LOSSLESS; // кодирование без потери качества
//                }
            case 'avif':
                $args[] = $quality;
                break;
            default:
                return null;
        }

        $function = '\\image' . $type;

        if (\function_exists($function)) {
            return $function(...$args);
        } else {
            return null;
        }
    }

    public function resize(mixed $image, int $maxW, int $maxH): mixed
    {
        if (! $this->ready) {
            throw new FileException('GD library not enabled');
        }

        $oldW   = \imagesx($image);
        $oldH   = \imagesy($image);
        $wr     = $maxW < 16 ? 1 : $maxW / $oldW;
        $hr     = $maxH < 16 ? 1 : $maxH / $oldH;
        $r      = \min($wr, $hr, 1);

        if (1 == $r) {
            return $image;
        }

        $width  = (int) \round($oldW * $r);
        $height = (int) \round($oldH * $r);

        if (false === ($result = \imagecreatetruecolor($width, $height))) {
            throw new FileException('Failed to create new truecolor image');
        }
        if (false === ($color = \imagecolorallocatealpha($result, 255, 255, 255, 127))) {
            throw new FileException('Failed to create color for image');
        }
        if (false === \imagefill($result, 0, 0, $color)) {
            throw new FileException('Failed to fill image with color');
        }

        \imagecolortransparent($result, $color);
        $palette = \imagecolorstotal($image);

        if (
            $palette > 0
            && ! \imagetruecolortopalette($result, true, $palette)
        ) {
            throw new FileException('Failed to convert image to palette');
        }

        $result = $this->tuning($result);

        if (false === \imagecopyresampled($result, $image, 0, 0, 0, 0, $width, $height, $oldW, $oldH)) {
            throw new FileException('Failed to resize image');
        }

        return $result;
    }
}
