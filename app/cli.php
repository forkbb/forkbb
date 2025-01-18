#!/usr/bin/php
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
use ForkBB\Core\ErrorHandlerCli;
use ForkBB\Models\Page;
use RuntimeException;
use function \ForkBB\__;

if (\PHP_SAPI !== 'cli') { // ???? в интернетах пишут, что этого недостаточно
    exit('This script wants to be run from the console. [' . \PHP_SAPI . ']' . \PHP_EOL);
}

\error_reporting(\E_ALL ^ \E_NOTICE);
\ini_set('display_errors', '1');
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
define('FORK_SFID', 2147483647);
define('FORK_CLI', true);

$loader       = require __DIR__ . '/../vendor/autoload.php';
$errorHandler = new ErrorHandlerCli();

if (\is_file(__DIR__ . '/config/main.php')) {
    $c = new Container(include __DIR__ . '/config/main.php');
    $a = [];
} elseif (\is_file(__DIR__ . '/config/install.php')) {
    $c = new Container(include __DIR__ . '/config/install.php');
    $a = ['install'];
} else {
    throw new RuntimeException('Application is not configured');
}

$c->autoloader = $loader;

$errorHandler->setContainer($c);

require __DIR__ . '/functions.php';
\ForkBB\_init($c);

$c->FORK_REVISION = 85;
$c->START         = $_SERVER['REQUEST_TIME_FLOAT'] ?? \microtime(true);
$c->PUBLIC_URL    = $c->BASE_URL . '/public';

\array_shift($argv);
$command = \array_shift($argv);

if (! \in_array($command, $a, true)) {
    exit('Invalid command. [' . $command . ']' . \PHP_EOL);
}

$arguments = [];

foreach ($argv as $value) {
    if (\preg_match('%^--([\w-]+)=(.*)$%', $value, $matches)) {
        if (
            isset($matches[2][1])
            && $matches[2][0] === $matches[2][-1]
            && (
                $matches[2][0] === '"'
                || $matches[2][0] === "'"
            )
        ) {
            $matches[2] = \substr($matches[2], 1, -1);
        }

        $arguments[$matches[1]] = $matches[2];
    }
}

switch ($command) {
    /**
     * Установка движка:
     * php cli.php install
     *   --installlang={en|es|fr|ru}
     *   --dbtype={mysql_innodb|mysql|pgsql|sqlite}
     *   --dbhost=DB_HOST
     *   --dbname=DB_NAME
     *   --dbuser=DB_USERNAME
     *   --dbpass=DB_PASSWORD
     *   --dbprefix=DB_PREFIX
     *   --username=ADMIN_LOGIN
     *   --password="ADMIN PASSWORD, A FEW WORDS, 16 CHARACTERS OR MORE"
     *   --email=ADMIN_EMAIL
     *   --title="FORUM TITLE"
     *   --descr={EMPTY|"FORUM DESCRIPTION"}
     *   --baseurl=FORUM_URL
     *   --defaultlang={en|es|fr|ru}
     *   --defaultstyle=ForkBB
     *   --cookie_domain=
     *   --cookie_path=/
     *   --cookie_secure={1|0}
     */
    case 'install':
        $c->user = $c->users->create(['id' => 1, 'group_id' => FORK_GROUP_ADMIN]);

        $c->Lang->load('common');
        $c->Router->add(
            $c->Router::DUO,
            '/admin/install',
            'Install:install',
            'Install'
        );

        $arguments['token'] = $c->Csrf->create('Install');
        $_POST              = $arguments;

        $result = $c->Install->install([], 'POST');

        if (empty($result->fIswev)) {
            $tpl = 'Ok';
        } else {
            $tpl = '---' . \PHP_EOL;

            foreach ($result->fIswev as $list) {
                foreach ($list as $cur) {
                    $tpl .= __($cur) . \PHP_EOL;
                }
            }

            $tpl = \html_entity_decode($tpl . '---' . \PHP_EOL, \ENT_HTML5 | \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        }

        break;
}

if ($c->isInit('DB')) {
    if ($c->DB->inTransaction()) {
        $c->DB->commit();
    }

    $c->DB->disconnect();
}

exit($tpl ?? 0);
