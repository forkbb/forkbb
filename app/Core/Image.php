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
use ForkBB\Core\File;
use ForkBB\Core\Exceptions\FileException;
use InvalidArgumentException;

class Image extends File
{
    /**
     * Изображение
     * @var false|resource
     */
    protected $image;

    /**
     * Качество изображения
     * @var int
     */
    protected $quality = 100;

    /**
     * Паттерн для pathinfo
     * @var string
     */
    protected $pattern = '%^(?!.*?\.\.)([\w.\x5C/:-]*[\x5C/])?(\*|[\w.-]+)\.(\*|[a-z\d]+|\([a-z\d]+(?:\|[a-z\d]+)*\))$%i';

    public function __construct(string $path, array $options)
    {
        parent::__construct($path, $options);

        if (
            ! \extension_loaded('gd')
            || ! \function_exists('\\imagecreatetruecolor')
        ) {
            throw new FileException('GD library not connected');
        }

        if (\is_string($this->data)) {
            $this->image = @\imagecreatefromstring($this->data);
        } else {
            $this->image = @\imagecreatefromstring(\file_get_contents($this->path));
        }

        if (false === $this->image) {
            throw new FileException('Invalid image data');
        }
    }

    /**
     * Изменяет размер изображения при необходимости
     */
    public function resize(int $maxW, int $maxH): Image
    {
        $oldW   = \imagesx($this->image);
        $oldH   = \imagesy($this->image);
        $wr     = ($maxW < 1) ? 1 : $maxW / $oldW;
        $hr     = ($maxH < 1) ? 1 : $maxH / $oldH;
        $r      = \min($wr, $hr, 1);
        $width  = (int) \round($oldW * $r);
        $height = (int) \round($oldH * $r);

        if (false === ($image = \imagecreatetruecolor($width, $height))) {
            throw new FileException('Failed to create new truecolor image');
        }
        if (false === ($color = \imagecolorallocatealpha($image, 255, 255, 255, 127))) {
            throw new FileException('Failed to create color for image');
        }
        if (false === \imagefill($image, 0, 0, $color)) {
            throw new FileException('Failed to fill image with color');
        }
        \imagecolortransparent($image, $color);
        $palette = \imagecolorstotal($this->image);
        if (
            $palette > 0
            && ! \imagetruecolortopalette($image, true, $palette)
        ) {
            throw new FileException('Failed to convert image to palette');
        }
        if (
            false === \imagealphablending($image, false)
            || false === \imagesavealpha($image, true)
        ) {
            throw new FileException('Failed to adjust image');
        }
        if (false === \imagecopyresampled($image, $this->image, 0, 0, 0, 0, $width, $height, $oldW, $oldH)) {
            throw new FileException('Failed to resize image');
        }

        $this->image = $image;

        return $this;
    }

    /**
     * Возвращает информацию о пути к сохраняемой картинке с учетом подстановок
     */
    protected function pathinfo(string $path): ?array
    {
        $info = parent::pathinfo($path);

        if (null === $info) {
            return null;
        }

        if (\is_array($info['extension'])) {
            if (\in_array($this->ext, $info['extension'])) {
                $info['extension'] = $this->ext;
            } else {
                $info['extension'] = \reset($info['extension']); // ???? выбор расширения?
            }
        }

        return $info;
    }

    /**
     * Создает/устанавливает права на картинку
     */
    protected function fileProc(string $path): bool
    {
        switch (\pathinfo($path, \PATHINFO_EXTENSION)) {
            case 'jpg':
                $result = @\imagejpeg($this->image, $path, $this->quality);
                break;
            case 'png':
                $quality = (int) \floor((100 - $this->quality) / 11);
                $result  = @\imagepng($this->image, $path, $quality);
                break;
            case 'gif':
                $result = @\imagegif($this->image, $path);
                break;
            case 'webp':
                $result = @\imagewebp($this->image, $path, $this->quality);
                break;
            default:
                $this->error = 'File type not supported';

                return false;
        }

        if (! $result) {
            $this->error = 'Error writing file';

            return false;
        }
        @\chmod($path, 0644);

        return true;
    }

    public function __destruct() {
        if (\is_resource($this->image)) {
            \imagedestroy($this->image);
        }
    }
}
