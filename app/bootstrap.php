<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB;

use ForkBB\Core\Container;
use ForkBB\Core\ErrorHandler;
use ForkBB\Models\Page;
use RuntimeException;

\error_reporting(\E_ALL ^ \E_NOTICE);
\ini_set('display_errors', '0');
\ini_set('log_errors', '1');

\setlocale(\LC_ALL, 'C');
\mb_language('uni');
\mb_internal_encoding('UTF-8');
\mb_substitute_character(0xFFFD);

define('FORK_GROUP_UNVERIFIED', 0);
define('FORK_GROUP_ADMIN', 1);
define('FORK_GROUP_MOD', 2);
define('FORK_GROUP_GUEST', 3);
define('FORK_GROUP_MEMBER', 4);

define('FORK_MESS_INFO', 'i');
define('FORK_MESS_SUCC', 's');
define('FORK_MESS_WARN', 'w');
define('FORK_MESS_ERR',  'e');
define('FORK_MESS_VLD',  'v');

define('FORK_GEN_NOT', 0);
define('FORK_GEN_MAN', 1);
define('FORK_GEN_FEM', 2);

define('FORK_JSON_ENCODE', \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

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

require __DIR__ . '/functions.php';
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

$c->FORK_REVISION = 77;
$c->START         = $forkStart;
$c->PUBLIC_URL    = $c->BASE_URL . $forkPublicPrefix;

$controllers = ['Primary', 'Routing'];

foreach ($controllers as $controller) {
    $page = $c->{$controller};

    if ($page instanceof Page) {
        break;
    }
}

if (null !== $page->onlinePos) {
    $c->Online->calc($page);
}

$tpl = $c->View->rendering($page);

if (
    $c->isInit('DB')
    && $c->DB->inTransaction()
) {
    $c->DB->commit();
}

if (
    null !== $tpl
    && 3 & $c->DEBUG
) {
    $debug = \rtrim($c->View->rendering($c->Debug->debug()));
    $tpl   = \str_replace('<!-- debuginfo -->', $debug, $tpl);
}

exit($tpl);
