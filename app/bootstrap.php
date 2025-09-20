<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB;

use ForkBB\Core\Container;
use ForkBB\Core\ErrorHandler;
use ForkBB\Core\EventDispatcher;
use ForkBB\Models\Page;
use RuntimeException;

require __DIR__ . '/functions.php';

define('FORK_CLI', false);

$loader       = require __DIR__ . '/../vendor/autoload.php';
$errorHandler = new ErrorHandler();

if (\is_file(__DIR__ . '/config/main.php')) {
    $c = new Container(include __DIR__ . '/config/main.php');
} elseif (\is_file(__DIR__ . '/config/install.php')) {
    $c = new Container(include __DIR__ . '/config/install.php');
} else {
    throw new RuntimeException('Application is not configured');
}

$c->autoloader = $loader;

$errorHandler->setContainer($c);
\ForkBB\_init($c);

// https or http?
if (
    ! empty($_SERVER['HTTPS'])
    && 'off' !== \strtolower($_SERVER['HTTPS'])
) {
    $c->BASE_URL = \str_replace('http://', 'https://', $c->BASE_URL);
} else {
    $c->BASE_URL = \str_replace('https://', 'http://', $c->BASE_URL);
}

$c->PUBLIC_URL    = $c->BASE_URL . $forkPublicPrefix;
$c->dispatcher    = new EventDispatcher($c);
$c->curReqVisible = 1;
$controllers      = ['Primary', 'Routing'];

foreach ($controllers as $controller) {
    $page = $c->{$controller};

    if ($page instanceof Page) {
        break;
    }
}

if (null !== $page->onlinePos) {
    $c->Online->calc($page);
}

if (null !== $page->nameTpl) {
    $page->prepare();
}

if ($c->isInit('DB')) {
    if ($c->DB->inTransaction()) {
        $c->DB->commit();
    }

    $c->DB->disconnect();
}

$tpl = $c->View->rendering($page);

if (
    null !== $tpl
    && 3 & $c->DEBUG
) {
    $debug = \rtrim($c->View->rendering($c->Debug->debug()));
    $tpl   = \str_replace('<!-- debuginfo -->', $debug, $tpl);
}

exit($tpl ?? 0);
