<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\PM;

class Cnst
{
    const ACTION_NEW     = 'new';
    const ACTION_CURRENT = 'current';
    const ACTION_ARCHIVE = 'archive';
    const ACTION_SEND    = 'send';
    const ACTION_TOPIC   = 'topic';
    const ACTION_DELETE  = 'delete';
    const ACTION_EDIT    = 'edit';
    const ACTION_BLOCK   = 'block';
    const ACTION_CONFIG  = 'config';
    const ACTION_POST    = 'post';

    const PT_START   = -1;
    const PT_DELETED = 0;
    const PT_NOTSENT = 1;
    const PT_NORMAL  = 2;
    const PT_ARCHIVE = 3;

    const PTOPIC = 0;
    const PPOST  = 1;
    const PRND   = 2;
}
