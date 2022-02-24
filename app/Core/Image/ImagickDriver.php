<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Core\Image;

use Imagick;
use ForkBB\Core\Files;
use ForkBB\Core\Image\DefaultDriver;
use ForkBB\Core\Exceptions\FileException;
use Exception;

class ImagickDriver extends DefaultDriver
{
    const DEFAULT = false;

    public function __construct(Files $files)
    {
        parent::__construct($files);

        $this->ready = \extension_loaded('imagick') && \class_exists('\\Imagick');
    }

    public function readFromStr(string $data) /* : mixed|false */
    {
        if ($this->ready) {
            try {
                $imagick = new Imagick();

                $imagick->readImageBlob($data);

                return $imagick;
            } catch (Exception $e) {
            }
        }

        return false;
    }

    public function readFromPath(string $path) /* : mixed|false */
    {
        if (
            ! $this->ready
            || $this->files->isBadPath($path)
        ) {
            return false;
        } else {
            try {
                return new Imagick(\realpath($path));
            } catch (Exception $e) {
                return false;
            }
        }
    }

    public function writeToPath(/* mixed */ $imagick, string $path, int $quality): ?bool
    {
        if (! $this->ready) {
            return null;
        }

        try {
            $type = \pathinfo($path, \PATHINFO_EXTENSION);

            switch ($type) {
                case 'png':
                    $imagick->setImageCompressionQuality(0); // ???? пересчитать как в GD?
                    break;
                default:
                    $imagick->setImageCompressionQuality($quality);
                    break;
            }

            return $imagick->writeImages($path, true);
        } catch (Exception $e) {
            return false;
        }
    }

    public function resize(/* mixed */ $imagick, int $maxW, int $maxH) /* : mixed */
    {
        if (! $this->ready) {
            throw new FileException('ImageMagick library not enabled');
        }

        try {
            $oldW   = $imagick->getImageWidth();
            $oldH   = $imagick->getImageHeight();
            $wr     = $maxW < 16 ? 1 : $maxW / $oldW;
            $hr     = $maxH < 16 ? 1 : $maxH / $oldH;
            $r      = \min($wr, $hr, 1);

            if (1 == $r) {
                return $imagick;
            }

            $width  = (int) \round($oldW * $r);
            $height = (int) \round($oldH * $r);

            // есть анимация
            if ($imagick->getImageDelay() > 0) {
                $images = $imagick->coalesceImages();

                foreach ($images as $frame) {
                    $frame->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
                    $frame->setImagePage($width, $height, 0, 0);
                }

                return $images->deconstructImages();
            // нет анимации
            } else {
                $imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);

                return $imagick;
            }
        } catch (Exception $e) {
            throw new FileException($e->getMessage());
        }
    }

    public function destroy(/* mixed */ $imagick): void
    {
    }
}
