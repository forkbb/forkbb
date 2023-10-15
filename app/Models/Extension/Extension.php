<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Extension;

use ForkBB\Models\Model;
use RuntimeException;

class Extension extends Model
{
    /**
     * Ключ модели для контейнера
     */
    protected string $cKey = 'Extension';

}
