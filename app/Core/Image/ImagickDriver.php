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

class ImagickDriver extends DefaultDriver
{
    const DEFAULT = false;

    public function __construct(Files $files)
    {
        parent::__construct($files);

        $this->ready = \extension_loaded('imagick') && \class_exists('\\Imagick');
    }


}
