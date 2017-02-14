<?php

namespace ForkBB;

use R2\DependencyInjection\Container;
use ForkBB\Models\Pages\Page;
use Exception;

if (! defined('PUN_ROOT'))
	exit('The constant PUN_ROOT must be defined and point to a valid FluxBB installation root directory.');

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

// The maximum size of a post, in characters, not bytes
if (!defined('PUN_MAX_POSTSIZE'))
	define('PUN_MAX_POSTSIZE', 65000);

if (!defined('PUN_SEARCH_MIN_WORD'))
	define('PUN_SEARCH_MIN_WORD', 3);
if (!defined('PUN_SEARCH_MAX_WORD'))
	define('PUN_SEARCH_MAX_WORD', 20);

if (!defined('FORUM_MAX_COOKIE_SIZE'))
	define('FORUM_MAX_COOKIE_SIZE', 4048);

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->setPsr4('ForkBB\\', __DIR__ . '/');

if (file_exists(__DIR__ . '/config/main.php')) {
    $container = new Container(include __DIR__ . '/config/main.php');
} elseif (file_exists(__DIR__ . '/config/install.php')) {
    $container = new Container(include __DIR__ . '/config/install.php');
} else {
    throw new Exception('Application is not configured');
}

define('PUN', 1);

$container->setParameter('DIR_CONFIG', __DIR__ . '/config');
$container->setParameter('DIR_CACHE', __DIR__ . '/cache');
$container->setParameter('DIR_VIEWS', __DIR__ . '/templates');
$container->setParameter('DIR_TRANSL', __DIR__ . '/lang');
$container->setParameter('START', $pun_start);

$config = $container->get('config');
$container->setParameter('date_formats', [$config['o_date_format'], 'Y-m-d', 'Y-d-m', 'd-m-Y', 'm-d-Y', 'M j Y', 'jS M Y']);
$container->setParameter('time_formats', [$config['o_time_format'], 'H:i:s', 'H:i', 'g:i:s a', 'g:i a']);

$page = null;
$controllers = ['Routing', 'Primary'];

while (! $page instanceof Page && $cur = array_pop($controllers)) {
    $page = $container->get($cur);
}

if ($page instanceof Page) { //????
    if ($page->getDataForOnline(true)) {
        $container->get('Online')->handle($page);
    }
    $tpl = $container->get('View')->setPage($page)->outputPage();
    if (defined('PUN_DEBUG')) {
        $debug = $container->get('Debug')->debug();
        $debug = $container->get('View')->setPage($debug)->outputPage();
        $tpl = str_replace('<!-- debuginfo -->', $debug, $tpl);
    }
    exit($tpl);
}
