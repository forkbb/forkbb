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

    public function readFromStr(string $data) /* : mixed|false */
    {
        return $this->ready ? \imagecreatefromstring($data) : false;
    }

    public function readFromPath(string $path) /* : mixed|false */
    {
        if (
            ! $this->ready
            || $this->files->isBadPath($path)
        ) {
            return false;
        } else {
            return \imagecreatefromstring(\file_get_contents($path));
        }
    }

    public function writeToPath(/* mixed */ $image, string $path, int $quality): ?bool
    {
        $args = [$image, $path];
        $type = \pathinfo($path, \PATHINFO_EXTENSION);

        switch ($type) {
            case 'gif':
                break;
            case 'png':
                $args[] = (int) \floor((100 - $quality) / 11);
                break;
            case 'jpg':
                $type = 'jpeg';
            case 'webp':
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

    public function resize(/* mixed */ $image, int $maxW, int $maxH) /* : mixed */
    {
        if (! $this->ready) {
            throw new FileException('GD library not enabled');
        }

        $oldW   = \imagesx($image);
        $oldH   = \imagesy($image);
        $wr     = ($maxW < 1) ? 1 : $maxW / $oldW;
        $hr     = ($maxH < 1) ? 1 : $maxH / $oldH;
        $r      = \min($wr, $hr, 1);
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
        if (
            false === \imagealphablending($result, false)
            || false === \imagesavealpha($result, true)
        ) {
            throw new FileException('Failed to adjust image');
        }
        if (false === \imagecopyresampled($result, $image, 0, 0, 0, 0, $width, $height, $oldW, $oldH)) {
            throw new FileException('Failed to resize image');
        }

        return $result;
    }

    public function destroy(/* mixed */ $image): void
    {
        if (\is_resource($image)) {
            \imagedestroy($image);
        }
    }
}
