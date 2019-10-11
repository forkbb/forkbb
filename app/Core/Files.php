<?php

namespace ForkBB\Core;

use ForkBB\Core\File;
use ForkBB\Core\Image;
use ForkBB\Core\Exceptions\FileException;
use InvalidArgumentException;

class Files
{
    /**
     * Максимальный размер для картинок
     * @var int
     */
    protected $maxImgSize;

    /**
     * Максимальный размер для файлов
     * @var int
     */
    protected $maxFileSize;

    /**
     * Текст ошибки
     * @var null|string
     */
    protected $error;

    /**
     * Список кодов типов картинок и расширений для них
     * @var array
     */
    protected $imageType = [
        1  => 'gif',
        2  => 'jpg',
        3  => 'png',
        4  => 'swf',
        5  => 'psd',
        6  => 'bmp',
        7  => 'tiff',
        8  => 'tiff',
        9  => 'jpc',
        10 => 'jp2',
        11 => 'jpx',
        12 => 'jb2',
        13 => 'swc',
        14 => 'iff',
        15 => 'wbmp',
        16 => 'xbm',
        17 => 'ico',
        18 => 'webp',
    ];

    /**
     * Конструктор
     *
     * @param string|int $maxFileSize
     * @param string|int $maxImgSize
     *
     */
    public function __construct($maxFileSize, $maxImgSize)
    {
        $init = \min(
            $this->size(\ini_get('upload_max_filesize')),
            $this->size(\ini_get('post_max_size'))
        );
        $this->maxImgSize = \min(
            $this->size($maxImgSize),
            $init
        );
        $this->maxFileSize = \min(
            $this->size($maxFileSize),
            $init
        );
    }

    /**
     * Возвращает максимальный размер картинки для загрузки
     *
     * @param string $unit
     *
     * @return int
     */
    public function maxImgSize($unit = null)
    {
        return $this->size($this->maxImgSize, $unit);
    }

    /**
     * Возвращает максимальный размер файла для загрузки
     *
     * @param string $unit
     *
     * @return int
     */
    public function maxFileSize($unit = null)
    {
        return $this->size($this->maxFileSize, $unit);
    }

    /**
     * Переводит объем информации из одних единиц в другие
     *
     * @param int|string $value
     * @param string $to
     *
     * @return int
     */
    public function size($value, $to = null)
    {
        if (\is_string($value)) {
            $value = \trim($value, "Bb \t\n\r\0\x0B");

            if (! isset($value[0])) {
                return 0;
            }

            $from = $value{\strlen($value) - 1};
            $value = (int) $value;

            switch ($from) {
                case 'G':
                case 'g':
                    $value *= 1024;
                case 'M':
                case 'm':
                    $value *= 1024;
                case 'K':
                case 'k':
                    $value *= 1024;
            }
        }

        if (\is_string($to)) {
            $to = \trim($to, "Bb \t\n\r\0\x0B");

            switch ($to) {
                case 'G':
                case 'g':
                    $value /= 1024;
                case 'M':
                case 'm':
                    $value /= 1024;
                case 'K':
                case 'k':
                    $value /= 1024;
            }
        }

        return (int) $value;
    }

    /**
     * Возвращает текст ошибки
     *
     * @return null|string
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * Определяет по содержимому файла расширение картинки
     *
     * @param mixed $file
     *
     * @return false|string
     */
    public function isImage($file)
    {
        if (\is_string($file)) {
            if (\function_exists('\exif_imagetype')) {
                $type = \exif_imagetype($file);
            } elseif (false !== ($type = @\getimagesize($file)) && $type[0] > 0 && $type[1] > 0) {
                $type = $type[2];
            } else {
                $type = 0;
            }
            return isset($this->imageType[$type]) ? $this->imageType[$type] : false;
        }

        return $file instanceof Image ? $file->ext() : false;
    }

    /**
     * Получает файл(ы) из формы
     *
     * @param array $file
     *
     * @return mixed
     */
    public function upload(array $file)
    {
        $this->error = null;

        if (! isset($file['tmp_name'])
            || ! isset($file['name'])
            || ! isset($file['type'])
            || ! isset($file['error'])
            || ! isset($file['size'])
        ) {
            $this->error = 'Expected file description array';
            return false;
        }

        if (\is_array($file['tmp_name'])) {
            $result = [];
            foreach ($file['tmp_name'] as $key => $value) {
                // изображение не было отправлено
                if ('' === $file['name'][$key] && empty($file['size'][$key])) {
                    continue;
                }

                $cur = $this->uploadOneFile([
                    'tmp_name' => $value,
                    'name'     => $file['name'][$key],
                    'type'     => $file['type'][$key],
                    'error'    => $file['error'][$key],
                    'size'     => $file['size'][$key],
                ]);

                if (false === $cur) {
                    return false;
                }

                $result[] = $cur;
            }
            return empty($result) ? null : $result;
        } else {
            return '' === $file['name'] && empty($file['size']) ? null : $this->uploadOneFile($file);
        }
    }

    /**
     * Получает один файл из формы
     *
     * @param array $file
     *
     * @return mixed
     */
    protected function uploadOneFile(array $file)
    {
        if (\UPLOAD_ERR_OK !== $file['error']) {
            switch ($file['error']) {
                case \UPLOAD_ERR_INI_SIZE:
                    $this->error = 'The uploaded file exceeds the upload_max_filesize';
                    break;
                case \UPLOAD_ERR_FORM_SIZE:
                    $this->error = 'The uploaded file exceeds the MAX_FILE_SIZE';
                    break;
                case \UPLOAD_ERR_PARTIAL:
                    $this->error = 'The uploaded file was only partially uploaded';
                    break;
                case \UPLOAD_ERR_NO_FILE:
                    $this->error = 'No file was uploaded';
                    break;
                case \UPLOAD_ERR_NO_TMP_DIR:
                    $this->error = 'Missing a temporary folder';
                    break;
                case \UPLOAD_ERR_CANT_WRITE:
                    $this->error = 'Failed to write file to disk';
                    break;
                case \UPLOAD_ERR_EXTENSION:
                    $this->error = 'A PHP extension stopped the file upload';
                    break;
                default:
                    $this->error = 'Unknown upload error';
                    break;
            }
            return false;
        }

        if (! \is_uploaded_file($file['tmp_name'])) {
            $this->error = 'The specified file was not uploaded';
            return false;
        }

        if (false === ($pos = \strrpos($file['name'], '.'))) {
            $name = $file['name'];
            $ext  = null;
        } else {
            $name = \substr($file['name'], 0, $pos);
            $ext  = \substr($file['name'], $pos + 1);
        }

        $isImage = $this->isImage($file['tmp_name']);

        if (false !== $isImage) {
            $ext     = $isImage;
            $isImage = 'swf' !== $isImage; // флеш не будет картинкой
        }

        if ($isImage) {
            if ($file['size'] > $this->maxImgSize) {
                $this->error = 'The image too large';
                return false;
            }
        } else {
            if ($file['size'] > $this->maxFileSize) {
                $this->error = 'The file too large';
                return false;
            }
        }

        $options = [
            'filename'  => $name,
            'extension' => $ext,
            'basename'  => $name . '.' . $ext,
            'mime'      => $file['type'],
//            'size'      => $file['size'],
        ];

        try {
            if ($isImage) {
                return new Image($file['tmp_name'], $options);
            } else {
                return new File($file['tmp_name'], $options);
            }
        } catch (FileException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
}
