<?php

namespace ForkBB;

use ForkBB\Core\Container;
use ForkBB\Core\ErrorHandler;
use ForkBB\Models\Page;
use RuntimeException;

\error_reporting(\E_ALL ^ \E_NOTICE);
\ini_set('display_errors', 0);
\ini_set('log_errors', 1);

\setlocale(\LC_ALL, 'C');
\mb_language('uni');
\mb_internal_encoding('UTF-8');
\mb_substitute_character(0xFFFD);

require __DIR__ . '/../vendor/autoload.php';

$errorHandler = new ErrorHandler();

if (\is_file(__DIR__ . '/config/main.php')) {
    $c = new Container(include __DIR__ . '/config/main.php');
} elseif (\is_file(__DIR__ . '/config/install.php')) {
    $c = new Container(include __DIR__ . '/config/install.php');
} else {
    throw new RuntimeException('Application is not configured');
}

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
$c->PUBLIC_URL = $c->BASE_URL . $forkPublicPrefix;

$c->FORK_REVISION = 7;
$c->START         = $forkStart;
$c->DIR_APP       = __DIR__;
$c->DIR_PUBLIC    = $forkPublic;
$c->DIR_CONFIG    = __DIR__ . '/config';
$c->DIR_CACHE     = __DIR__ . '/cache';
$c->DIR_VIEWS     = __DIR__ . '/templates';
$c->DIR_LANG      = __DIR__ . '/lang';
$c->DATE_FORMATS  = ['Y-m-d', 'd M Y', 'Y-m-d', 'Y-d-m', 'd-m-Y', 'm-d-Y', 'M j Y', 'jS M Y'];
$c->TIME_FORMATS  = ['H:i:s', 'H:i', 'H:i:s', 'H:i', 'g:i:s a', 'g:i a'];

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

if ($c->isInit('DB')) {
    $c->DB->commit();
}

if (
    null !== $tpl
    && $c->DEBUG > 0
) {
    $debug = $c->View->rendering($c->Debug->debug());
    $tpl   = \str_replace('<!-- debuginfo -->', $debug, $tpl);
}

exit($tpl);
