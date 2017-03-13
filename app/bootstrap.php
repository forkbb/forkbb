<?php

namespace ForkBB;

use ForkBB\Core\Container;
use ForkBB\Models\Pages\Page;
use RuntimeException;

// боевой
#error_reporting(E_ALL);
#ini_set('display_errors', 0);
#ini_set('log_errors', 1);
// разраб
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

mb_language('uni');
mb_internal_encoding('UTF-8');
mb_substitute_character(0xFFFD);

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/config/main.php')) {
    $c = new Container(include __DIR__ . '/config/main.php');
} elseif (file_exists(__DIR__ . '/config/install.php')) {
    $c = new Container(include __DIR__ . '/config/install.php');
} else {
    throw new RuntimeException('Application is not configured.');
}

require __DIR__ . '/functions.php';

$c->FORK_REVISION = 1;
$c->START = $forkStart;
$c->DIR_APP    = __DIR__;
$c->DIR_PUBLIC = $forkPublic;
$c->DIR_CONFIG = __DIR__ . '/config';
$c->DIR_CACHE  = __DIR__ . '/cache';
$c->DIR_VIEWS  = __DIR__ . '/templates';
$c->DIR_LANG   = __DIR__ . '/lang';
$c->DATE_FORMATS = [$c->config['o_date_format'], 'Y-m-d', 'Y-d-m', 'd-m-Y', 'm-d-Y', 'M j Y', 'jS M Y'];
$c->TIME_FORMATS = [$c->config['o_time_format'], 'H:i:s', 'H:i', 'g:i:s a', 'g:i a'];

$controllers = ['Routing', 'Primary'];
$page = null;
while (! $page instanceof Page && $cur = array_pop($controllers)) {
    $page = $c->$cur;
}

if ($page->getDataForOnline(true)) {
    $c->Online->handle($page);
}
$tpl = $c->View->setPage($page)->outputPage();
if ($c->DEBUG > 0) {
    $debug = $c->Debug->debug();
    $debug = $c->View->setPage($debug)->outputPage();
    $tpl = str_replace('<!-- debuginfo -->', $debug, $tpl);
}
exit($tpl);
