#!/usr/bin/php
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
use ForkBB\Core\ErrorHandlerCli;
use ForkBB\Models\Page;
use RuntimeException;
use function \ForkBB\__;

require __DIR__ . '/functions.php';

if (\PHP_SAPI !== 'cli') {
    \error_log('cli.php SAPI = ' . \PHP_SAPI);

    exit('This script wants to be run from the console. [' . \PHP_SAPI . ']' . \PHP_EOL);
}

define('FORK_CLI', true);

$loader       = require __DIR__ . '/../vendor/autoload.php';
$errorHandler = new ErrorHandlerCli();

if (\is_file(__DIR__ . '/config/main.php')) {
    $c = new Container(include __DIR__ . '/config/main.php');
    $a = ['test', 'send_mail'];
} elseif (\is_file(__DIR__ . '/config/install.php')) {
    $c = new Container(include __DIR__ . '/config/install.php');
    $a = ['install'];
} else {
    throw new RuntimeException('Application is not configured');
}

$c->autoloader = $loader;

$errorHandler->setContainer($c);
\ForkBB\_init($c);

$c->PUBLIC_URL = $c->BASE_URL . '/public';

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
    /**
     * Тест, выводит запись о вызове в лог движка:
     * php cli.php test
     */
    case 'test':
        $c->user = $c->users->create(['id' => 0, 'group_id' => FORK_GROUP_GUEST]);

        $c->Lang->load('common');
        $c->Log->debug('CLI test done', [
            'PHP'     => \PHP_VERSION,
            'SAPI'    => \PHP_SAPI,
            'time'    => \number_format(\microtime(true) - $c->START, 3, '.', ''),
            'user'    => $c->user->fLog(),
            'headers' => false,
        ]);

        $tpl = 'Ok';

        break;
    /**
     * Отправка писем из очереди
     * php cli.php send_mail [--log={1|2}]
     *
     * --log=1 - пишет в лог, если есть выполненные задания или скрипт не смог получить эксклюзивную блокировку
     * --log=2 - пишет в лог в любом случае
     */
    case 'send_mail':
        $c->user = $c->users->create(['id' => 0, 'group_id' => FORK_GROUP_GUEST]);

        $result = $c->MailQueue->execute(function (array $data) use ($c) {
            return $c->Mail->send($data);
        });

        if (
            ! empty($arguments['log'])
            && (
                '2' == $arguments['log']
                || (
                    '1' == $arguments['log']
                    && 0 !== $result
                )
            )
        ) {
            $c->Log->debug('CLI send_mail. Tasks completed: ' . (false === $result ? 'false' : $result), [
                'time'    => \number_format(\microtime(true) - $c->START, 3, '.', ''),
                'headers' => false,
            ]);
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
