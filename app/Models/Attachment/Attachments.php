<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Attachment;

use ForkBB\Models\Manager;
use ForkBB\Models\User\User;
use RuntimeException;

class Attachments extends Manager
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Attachments';

}
