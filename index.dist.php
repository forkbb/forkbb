<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

$forkStart = empty($_SERVER['REQUEST_TIME_FLOAT']) ? \microtime(true) : $_SERVER['REQUEST_TIME_FLOAT'];
$forkPublic = __DIR__ . '/public';
$forkPublicPrefix = '/public';

require __DIR__ . '/app/bootstrap.php';
