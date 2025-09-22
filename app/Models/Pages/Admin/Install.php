<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use PDO;
use PDOException;
use RuntimeException;
use function \ForkBB\__;

class Install extends Admin
{
    const PHP_MIN    = '8.0.0';
    const MYSQL_MIN  = '5.5.3';
    const SQLITE_MIN = '3.25.0';
    const PGSQL_MIN  = '10.0';

    /**
     * Для MySQL
     */
    protected string $DBEngine = '';

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
    }

    /**
     * Возращает доступные типы БД
     */
    protected function DBTypes(): array
    {
        $dbTypes    = [];
        $pdoDrivers = PDO::getAvailableDrivers();

        foreach ($pdoDrivers as $type) {
            if (\is_file($this->c->DIR_APP . '/Core/DB/' . \ucfirst($type) . '.php')) {
                switch ($type) {
                    case 'mysql':
                        $dbTypes['mysql_innodb'] = 'MySQL InnoDB (PDO)';
                        $dbTypes[$type]          = 'MySQL (PDO) (no transactions!)';

                        break;
                    case 'sqlite':
                        $dbTypes[$type]          = 'SQLite (PDO)';

                        break;
                    case 'pgsql':
                        $dbTypes[$type]          = 'PostgreSQL (PDO)';

                        break;
                    default:
                        $dbTypes[$type]          = \ucfirst($type) . ' (PDO)';

                        break;
                }
            }
        }

        return $dbTypes;
    }

    /**
     * Подготовка данных для страницы установки форума
     */
    public function install(array $args, string $method): Page
    {
        $changeLang = false;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'       => 'token:Install',
                    'installlang' => 'required|string:trim',
                    'changelang'  => 'string', // не нужно required
                ]);

            if ($v->validation($_POST)) {
                $this->user->language = $v->installlang;
                $changeLang           = (bool) $v->changelang;
            }
        }

        $v = null;

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_install');

        // версия PHP
        if (\version_compare(\PHP_VERSION, self::PHP_MIN, '<')) {
            $this->fIswev = [FORK_MESS_ERR, ['You are running error', 'PHP', \PHP_VERSION, FORK_REVISION, self::PHP_MIN]];
        }

        // типы БД
        $this->dbTypes = $this->DBTypes();

        if (empty($this->dbTypes)) {
            $this->fIswev = [FORK_MESS_ERR, 'No DB extensions'];
        }

        // доступность папок на запись
        $folders = [
            $this->c->DIR_CONFIG,
            $this->c->DIR_CONFIG . '/db',
            $this->c->DIR_CONFIG . '/ext',
            $this->c->DIR_CACHE,
            $this->c->DIR_CACHE . '/polls',
            $this->c->DIR_PUBLIC . '/img/avatars',
            $this->c->DIR_PUBLIC . '/upload',
            $this->c->DIR_LOG,
        ];

        foreach ($folders as $folder) {
            if (! \is_writable($folder)) {
                $folder       = \str_replace(\dirname($this->c->DIR_APP), '', $folder);
                $this->fIswev = [FORK_MESS_ERR, ['Alert folder %s', $folder]];
            }
        }

        // доступность шаблона конфигурации
        $config = \file_get_contents($this->c->DIR_CONFIG . '/main.dist.php');

        if (false === $config) {
            $this->fIswev = [FORK_MESS_ERR, 'No access to main.dist.php'];
        }

        unset($config);

        // языки
        $langs = $this->c->Func->getNameLangs();

        if (empty($langs)) {
            $this->fIswev = [FORK_MESS_ERR, 'No language packs'];
        }

        // стили
        $styles = $this->c->Func->getStyles();

        if (empty($styles)) {
            $this->fIswev = [FORK_MESS_ERR, 'No styles'];
        }

        if (
            'POST' === $method
            && ! $changeLang
            && empty($this->fIswev[FORK_MESS_ERR])
        ) { //????
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_prefix'  => [$this, 'vCheckPrefix'],
                    'check_host'    => [$this, 'vCheckHost'],
                    'rtrim_url'     => [$this, 'vRtrimURL']
                ])->addRules([
                    'token'         => 'token:Install',
                    'dbtype'        => 'required|string:trim|in:' . \implode(',', \array_keys($this->dbTypes)),
                    'dbhost'        => 'required|string:trim|check_host',
                    'dbname'        => 'required|string:trim',
                    'dbuser'        => 'exist|string:trim',
                    'dbpass'        => 'exist|string:trim',
                    'dbprefix'      => 'required|string:trim|min:1|max:40|check_prefix',
                    'username'      => 'required|string:trim|min:2|max:25',
                    'password'      => 'required|string|min:16|max:100000|password',
                    'email'         => 'required|string:trim|email',
                    'title'         => 'required|string:trim|max:255',
                    'descr'         => 'exist|string:trim,empty|max:65000 bytes|html',
                    'baseurl'       => 'required|string:trim|rtrim_url|max:128',
                    'defaultlang'   => [
                        'required',
                        'string:trim',
                        'in' => $this->c->Func->getLangs(),
                    ],
                    'defaultstyle'  => [
                        'required',
                        'string:trim',
                        'in' => $this->c->Func->getStyles(),
                    ],
                    'cookie_domain' => 'exist|string:trim|max:128',
                    'cookie_path'   => 'required|string:trim|max:1024',
                    'cookie_secure' => 'required|integer|in:0,1',
                ])->addAliases([
                    'dbtype'        => 'Database type',
                    'dbhost'        => 'Database server hostname',
                    'dbname'        => 'Database name',
                    'dbuser'        => 'Database username',
                    'dbpass'        => 'Database password',
                    'dbprefix'      => 'Table prefix',
                    'username'      => 'Administrator username',
                    'password'      => 'Administrator passphrase',
                    'title'         => 'Board title',
                    'descr'         => 'Board description',
                    'baseurl'       => 'Base URL',
                    'defaultlang'   => 'Default language',
                    'defaultstyle'  => 'Default style',
                    'cookie_domain' => 'Cookie Domain',
                    'cookie_path'   => 'Cookie Path',
                    'cookie_secure' => 'Cookie Secure',
                ])->addMessages([
                    'email'         => 'Wrong email',
                ]);

            if ($v->validation($_POST)) {
                return $this->installEnd($v);

            } else {
                $this->fIswev = $v->getErrors();
            }
        }

        if (\count($langs) > 1) {
            $this->form1 = [
                'action' => $this->c->Router->link('Install'),
                'hidden' => [
                    'token' => $this->c->Csrf->create('Install'),
                ],
                'sets'   => [
                    'dlang' => [
                        'fields' => [
                            'installlang' => [
                                'type'    => 'select',
                                'options' => $langs,
                                'value'   => $this->user->language,
                                'caption' => 'Install language',
                                'help'    => 'Choose install language info',
                            ],
                        ],
                    ],
                ],
                'btns'   => [
                    'changelang'  => [
                        'type'  => 'submit',
                        'value' => __('Change language'),
                    ],
                ],
            ];
        }

        $this->form2 = [
            'action' => $this->c->Router->link('Install'),
            'hidden' => [
                'token'       => $this->c->Csrf->create('Install'),
                'installlang' => $this->user->language,
            ],
            'sets'   => [
                'db-info' => [
                    'inform' => [
                        [
                            'message' => 'Database setup',
                        ],
                        [
                            'message' => 'Info 1',
                        ],
                    ],
                ],
                'db' => [
                    'fields' => [
                        'dbtype' => [
                            'type'     => 'select',
                            'options'  => $this->dbTypes,
                            'value'    => $v->dbtype ?? 'mysql_innodb',
                            'caption'  => 'Database type',
                            'help'     => 'Info 2',
                        ],
                        'dbhost' => [
                            'type'     => 'text',
                            'value'    => $v->dbhost ?? 'localhost',
                            'caption'  => 'Database server hostname',
                            'help'     => 'Info 3',
                            'required' => true,
                        ],
                        'dbname' => [
                            'type'     => 'text',
                            'value'    => $v->dbname ?? '',
                            'caption'  => 'Database name',
                            'help'     => 'Info 4',
                            'required' => true,
                        ],
                        'dbuser' => [
                            'type'    => 'text',
                            'value'   => $v->dbuser ?? '',
                            'caption' => 'Database username',
                        ],
                        'dbpass' => [
                            'type'    => 'password',
                            'value'   => '',
                            'caption' => 'Database password',
                            'help'    => 'Info 5',
                        ],
                        'dbprefix' => [
                            'type'      => 'text',
                            'maxlength' => '40',
                            'value'     => $v->dbprefix ?? '',
                            'caption'   => 'Table prefix',
                            'help'      => 'Info 6',
                            'required' => true,
                        ],
                    ],
                ],
                'adm-info' => [
                    'inform' => [
                        [
                            'message' => 'Administration setup',
                        ],
                        [
                            'message' => 'Info 7',
                        ],
                    ],
                ],
                'adm' => [
                    'fields' => [
                        'username' => [
                            'type'      => 'text',
                            'maxlength' => '25',
                            'pattern'   => '^.{2,25}$',
                            'value'     => $v->username ?? '',
                            'caption'   => 'Administrator username',
                            'help'      => 'Info 8',
                            'required'  => true,
                        ],
                        'password' => [
                            'type'     => 'password',
                            'pattern'  => '^.{16,}$',
                            'value'    => '',
                            'caption'  => 'Administrator passphrase',
                            'help'     => 'Info 9',
                            'required' => true,
                        ],
                        'email' => [
                            'type'           => 'text',
                            'maxlength'      => '80',
                            'pattern'        => '.+@.+',
                            'value'          => $v->email ?? '',
                            'caption'        => 'Administrator email',
                            'help'           => 'Info 10',
                            'required'       => true,
                            'autocapitalize' => 'off',
                        ],

                    ],
                ],
                'board-info' => [
                    'inform' => [
                        [
                            'message' => 'Board setup',
                        ],
                        [
                            'message' => 'Info 11',
                        ],
                    ],
                ],
                'board' => [
                    'fields' => [
                        'title' => [
                            'type'      => 'text',
                            'maxlength' => '255',
                            'value'     => $v->title ?? __('My ForkBB Forum'),
                            'caption'   => 'Board title',
                            'required'  => true,
                        ],
                        'descr' => [
                            'type'      => 'text',
                            'maxlength' => '16000',
                            'value'     => $v->descr ?? __('Description'),
                            'caption'   => 'Board description',
                        ],
                        'baseurl' => [
                            'type'      => 'text',
                            'maxlength' => '128',
                            'value'     => $v->baseurl ?? $this->c->BASE_URL,
                            'caption'   => 'Base URL',
                            'required'  => true,
                        ],
                        'defaultlang' => [
                            'type'      => 'select',
                            'options'   => $langs,
                            'value'     => $v->defaultlang ?? $this->user->language,
                            'caption'   => 'Default language',
                        ],
                        'defaultstyle' => [
                            'type'      => 'select',
                            'options'   => $styles,
                            'value'     => $v->defaultstyle ?? $this->user->style,
                            'caption'   => 'Default style',
                        ],
                    ],
                ],
                'cookie-info' => [
                    'inform' => [
                        [
                            'message' => 'Cookie setup',
                        ],
                        [
                            'message' => 'Info 12',
                        ],
                    ],
                ],
                'cookie' => [
                    'fields' => [
                        'cookie_domain' => [
                            'type'      => 'text',
                            'maxlength' => '128',
                            'value'     => $v->cookie_domain ?? '',
                            'caption'   => 'Cookie Domain',
                            'help'      => 'Cookie Domain info',
                        ],
                        'cookie_path' => [
                            'type'      => 'text',
                            'maxlength' => '1024',
                            'value'     => $v
                                ? $v->cookie_path
                                : \rtrim((string) \parse_url($this->c->BASE_URL, \PHP_URL_PATH), '/') . '/',
                            'caption'   => 'Cookie Path',
                            'help'      => 'Cookie Path info',
                            'required'  => true,
                        ],
                        'cookie_secure' => [
                            'type'    => 'radio',
                            'value'   => $v
                                ? $v->cookie_secure
                                : (
                                    \preg_match('%^https%i', $this->c->BASE_URL)
                                    ? 1
                                    : 0
                                ),
                            'values'  => [1 => __('Yes '), 0 => __('No ')],
                            'caption' => 'Cookie Secure',
                            'help'    => 'Cookie Secure info',
                        ],

                    ],
                ],
            ],
            'btns'   => [
                'submit'  => [
                    'type'  => 'submit',
                    'value' => __('Start install'),
                ],
            ],
        ];

        $this->nameTpl   = 'layouts/install';
        $this->onlinePos = null;

        return $this;
    }

    /**
     * Обработка base URL
     */
    public function vRtrimURL(Validator $v, string $url): string
    {
        return \rtrim($url, '/');
    }

    /**
     * Дополнительная проверка префикса
     */
    public function vCheckPrefix(Validator $v, string $prefix): string
    {
        if (! \preg_match('%^[a-z][a-z\d_]*$%i', $prefix)) {
            $v->addError('Table prefix error');

        } elseif (
            'sqlite_' === \strtolower($prefix)
            || 'pg_' === \strtolower($prefix)
        ) {
            $v->addError('Prefix reserved');
        }

        return $prefix;
    }

    /**
     * Полная проверка подключения к БД
     */
    public function vCheckHost(Validator $v, string $dbhost): string
    {
        $this->c->DB_USERNAME    = $v->dbuser;
        $this->c->DB_PASSWORD    = $v->dbpass;
        $this->c->DB_OPTIONS     = [];
        $this->c->DB_OPTS_AS_STR = '';
        $this->c->DB_PREFIX      = $v->dbprefix;
        $dbtype                  = $v->dbtype;
        $dbname                  = $v->dbname;

        // есть ошибки, ни чего не проверяем
        if (! empty($v->getErrors())) {
            return $dbhost;
        }

        // настройки подключения БД
        $DBEngine = 'MyISAM';

        switch ($dbtype) {
            case 'mysql_innodb':
                $DBEngine = 'InnoDB';
            case 'mysql':
                $this->DBEngine = $DBEngine;

                if (\preg_match('%^([^:]+):(\d+)$%', $dbhost, $matches)) {
                    $this->c->DB_DSN = "mysql:host={$matches[1]};port={$matches[2]};dbname={$dbname};charset=utf8mb4";

                } else {
                    $this->c->DB_DSN = "mysql:host={$dbhost};dbname={$dbname};charset=utf8mb4";
                }

                break;
            case 'sqlite':
                $this->c->DB_DSN         = "sqlite:!PATH!{$dbname}";
                $this->c->DB_OPTS_AS_STR = "\n"
                    . '        \\PDO::ATTR_TIMEOUT => 5,' . "\n"
                    . '        /* \'initSQLCommands\' => [\'PRAGMA journal_mode=WAL\',], */' . "\n"
                    . '        \'initFunction\' => function ($db) {return $db->sqliteCreateFunction(\'CONCAT\', function (...$args) {return \\implode(\'\', $args);});},' . "\n"
                    . '    ';
                $this->c->DB_OPTIONS     = [
                    PDO::ATTR_TIMEOUT => 5,
                    'initSQLCommands' => [
                        'PRAGMA journal_mode=WAL',
                    ],
                    'initFunction' => function ($db) {return $db->sqliteCreateFunction('CONCAT', function (...$args) {return \implode('', $args);});},
                ];

                break;
            case 'pgsql':
                if (\preg_match('%^([^:]+):(\d+)$%', $dbhost, $matches)) {
                    $host = $matches[1];
                    $port = $matches[2];

                } else {
                    $host = $dbhost;
                    $port = '5432';
                }

                $this->c->DB_DSN = "pgsql:host={$host} port={$port} dbname={$dbname} options='--client_encoding=UTF8'";

                break;
            default:
                //????
                break;
        }

        // подключение к БД
        try {
            $stat = $this->c->DB->statistics();
        } catch (PDOException $e) {
            $v->addError($e->getMessage());

            return $dbhost;
        }

        $version = $versionNeed = $this->c->DB->getAttribute(PDO::ATTR_SERVER_VERSION);

        switch ($dbtype) {
            case 'mysql_innodb':
            case 'mysql':
                $versionNeed = self::MYSQL_MIN;
                $progName    = 'MySQL';

                break;
            case 'sqlite':
                $versionNeed = self::SQLITE_MIN;
                $progName    = 'SQLite';

                break;
            case 'pgsql':
                $versionNeed = self::PGSQL_MIN;
                $progName    = 'PostgreSQL';

                break;
        }

        if (\version_compare($version, $versionNeed, '<')) {
            $v->addError(['You are running error', $progName, $version, FORK_REVISION, $versionNeed]);

            return $dbhost;
        }

        // проверка наличия таблиц в БД
        if (
            '' != $stat['tables']
            && '0' != $stat['tables']
        ) {
            $v->addError(['Existing table error', $v->dbprefix, $v->dbname, $stat['tables']]);

            return $dbhost;
        }

        // база MySQL, кодировка базы отличается от UTF-8 (4 байта)
        if (
            isset($stat['character_set_database'])
            && 'utf8mb4' !== $stat['character_set_database']
        ) {
            $v->addError('Bad database charset');
        }

        // база PostgreSQL, кодировка базы
        if (
            isset($stat['server_encoding'])
            && 'UTF8' !== $stat['server_encoding']
        ) {
            $v->addError(['Bad database encoding', 'UTF8']);
        }

        // база PostgreSQL, порядок сопоставления/сортировки
        if (
            isset($stat['lc_collate'])
            && 'C' !== $stat['lc_collate']
        ) {
            $v->addError('Bad database collate');
        }

        // база PostgreSQL, тип символов
        if (
            isset($stat['lc_ctype'])
            && 'C' !== $stat['lc_ctype']
        ) {
            $v->addError('Bad database ctype');
        }

        // база SQLite, кодировка базы
        if (
            isset($stat['encoding'])
            && 'UTF-8' !== $stat['encoding']
        ) {
            $v->addError(['Bad database encoding', 'UTF-8']);
        }

        // тест типа возвращаемого результата
        $table  = '::tmp_test';
        $schema = [
            'FIELDS' => [
                'test_field' => ['INT(10) UNSIGNED', false, 0],
            ],
            'ENGINE' => $this->DBEngine,
        ];

        if (! $this->c->DB->createTable($table, $schema)) {
            $v->addError('Failed to create table');

        } else {
            if (! $this->c->DB->truncateTable($table)) {
                $v->addError('Failed to truncate table');
            }

            $this->c->DB->exec("INSERT INTO {$table} (test_field) VALUES (?i)", [123]);

            $data = $this->c->DB->query("SELECT test_field FROM {$table} WHERE test_field=123")->fetch();

            if (123 !== $data['test_field']) {
                $v->addError('Wrong data type for numeric fields');
            }

            if (! $this->c->DB->dropTable($table)) {
                $v->addError('Failed to drop table');
            }
        }

        return $dbhost;
    }

    /**
     * Завершение установки форума
     */
    protected function installEnd(Validator $v): Page
    {
        if (\function_exists('\\set_time_limit')) {
            \set_time_limit(0);
        }

        if (true !== $this->c->Cache->clear()) {
            throw new RuntimeException('Unable to clear cache');
        }

        if ('pgsql' === $this->c->DB->getType()) {
            $query = 'CREATE COLLATION IF NOT EXISTS fork_icu (
                provider = icu,
                locale = \'und-u-ks-level2\'
            )';

            $this->c->DB->exec($query);
        }

        //attachments
        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'uid'         => ['INT(10) UNSIGNED', false, 0],
                'created'     => ['INT(10) UNSIGNED', false, 0],
                'size_kb'     => ['INT(10) UNSIGNED', false, 0],
                'path'        => ['VARCHAR(255)', false, ''],
                'uip'         => ['VARCHAR(45)', false, ''],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'uid_idx' => ['uid'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::attachments', $schema);

        //attachments_pos
        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'pid'         => ['INT(10) UNSIGNED', false, 0],
            ],
            'UNIQUE KEYS' => [
                'id_pid_idx' => ['id', 'pid'],
            ],
            'INDEXES' => [
                'pid_idx' => ['pid'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::attachments_pos', $schema);

        //attachments_pos_pm
        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'pid'         => ['INT(10) UNSIGNED', false, 0],
            ],
            'UNIQUE KEYS' => [
                'id_pid_idx' => ['id', 'pid'],
            ],
            'INDEXES' => [
                'pid_idx' => ['pid'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::attachments_pos_pm', $schema);

        // bans
        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'username'    => ['VARCHAR(190)', false, ''],
                'ip'          => ['VARCHAR(255)', false, ''],
                'email'       => ['VARCHAR(190)', false, ''],
                'message'     => ['VARCHAR(255)', false, ''],
                'expire'      => ['INT(10) UNSIGNED', false, 0],
                'ban_creator' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'username_idx' => ['username(25)'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::bans', $schema);

        // bbcode
        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'bb_tag'       => ['VARCHAR(11)', false, ''],
                'bb_edit'      => ['TINYINT(1)', false, 1],
                'bb_delete'    => ['TINYINT(1)', false, 1],
                'bb_structure' => ['MEDIUMTEXT', false],
            ],
            'PRIMARY KEY' => ['id'],
            'UNIQUE KEYS' => [
                'bb_tag_idx' => ['bb_tag'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::bbcode', $schema);

        // categories
        $schema = [
            'FIELDS' => [
                'id'            => ['SERIAL', false],
                'cat_name'      => ['VARCHAR(80)', false, 'New Category'],
                'disp_position' => ['INT(10)', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::categories', $schema);

        // censoring
        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'search_for'   => ['VARCHAR(60)', false, ''],
                'replace_with' => ['VARCHAR(60)', false, ''],
            ],
            'PRIMARY KEY' => ['id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::censoring', $schema);

        // config
        $schema = [
            'FIELDS' => [
                'conf_name'  => ['VARCHAR(190)', false, ''],
                'conf_value' => ['TEXT', true],
            ],
            'PRIMARY KEY' => ['conf_name'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::config', $schema);

        // extensions
        $schema = [
            'FIELDS' => [
                'ext_name'   => ['VARCHAR(190)', false, ''],
                'ext_status' => ['TINYINT', false, 0],
                'ext_data'   => ['TEXT', false],
            ],
            'PRIMARY KEY' => ['ext_name'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::extensions', $schema);

        // forum_perms
        $schema = [
            'FIELDS' => [
                'group_id'     => ['INT(10)', false, 0],
                'forum_id'     => ['INT(10)', false, 0],
                'read_forum'   => ['TINYINT(1)', false, 1],
                'post_replies' => ['TINYINT(1)', false, 1],
                'post_topics'  => ['TINYINT(1)', false, 1],
            ],
            'PRIMARY KEY' => ['group_id', 'forum_id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::forum_perms', $schema);

        // forums
        $schema = [
            'FIELDS' => [
                'id'                => ['SERIAL', false],
                'forum_name'        => ['VARCHAR(80)', false, 'New forum'],
                'friendly_name'     => ['VARCHAR(80)', false, ''],
                'forum_desc'        => ['TEXT', false],
                'redirect_url'      => ['VARCHAR(255)', false, ''],
                'moderators'        => ['TEXT', false],
                'num_topics'        => ['INT(10) UNSIGNED', false, 0],
                'num_posts'         => ['INT(10) UNSIGNED', false, 0],
                'last_post'         => ['INT(10) UNSIGNED', false, 0],
                'last_post_id'      => ['INT(10) UNSIGNED', false, 0],
                'last_poster'       => ['VARCHAR(190)', false, ''],
                'last_poster_id'    => ['INT(10) UNSIGNED', false, 0],
                'last_topic'        => ['VARCHAR(255)', false, ''],
                'sort_by'           => ['TINYINT(1)', false, 0],
                'disp_position'     => ['INT(10)', false, 0],
                'cat_id'            => ['INT(10) UNSIGNED', false, 0],
                'no_sum_mess'       => ['TINYINT(1)', false, 0],
                'parent_forum_id'   => ['INT(10) UNSIGNED', false, 0],
                'use_solution'      => ['TINYINT(1)', false, 0],
                'premoderation'     => ['TINYINT', false, 0],
                'use_custom_fields' => ['TINYINT(1)', false, 0],
                'custom_fields'     => ['TEXT', true],
            ],
            'PRIMARY KEY' => ['id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::forums', $schema);

        // groups
        $schema = [
            'FIELDS' => [
                'g_id'                   => ['SERIAL', false],
                'g_title'                => ['VARCHAR(50)', false, ''],
                'g_user_title'           => ['VARCHAR(50)', false, ''],
                'g_promote_min_posts'    => ['INT(10) UNSIGNED', false, 0],
                'g_promote_next_group'   => ['INT(10) UNSIGNED', false, 0],
                'g_moderator'            => ['TINYINT(1)', false, 0],
                'g_mod_edit_users'       => ['TINYINT(1)', false, 0],
                'g_mod_rename_users'     => ['TINYINT(1)', false, 0],
                'g_mod_change_passwords' => ['TINYINT(1)', false, 0],
                'g_mod_ban_users'        => ['TINYINT(1)', false, 0],
                'g_mod_promote_users'    => ['TINYINT(1)', false, 0],
                'g_read_board'           => ['TINYINT(1)', false, 1],
                'g_view_users'           => ['TINYINT(1)', false, 1],
                'g_post_replies'         => ['TINYINT(1)', false, 1],
                'g_post_topics'          => ['TINYINT(1)', false, 1],
                'g_edit_posts'           => ['TINYINT(1)', false, 1],
                'g_delete_posts'         => ['TINYINT(1)', false, 1],
                'g_delete_topics'        => ['TINYINT(1)', false, 1],
                'g_delete_profile'       => ['TINYINT(1)', false, 0],
                'g_post_links'           => ['TINYINT(1)', false, 1],
                'g_set_title'            => ['TINYINT(1)', false, 1],
                'g_search'               => ['TINYINT(1)', false, 1],
                'g_search_users'         => ['TINYINT(1)', false, 1],
                'g_send_email'           => ['TINYINT(1)', false, 1],
                'g_post_flood'           => ['SMALLINT(6)', false, 30],
                'g_search_flood'         => ['SMALLINT(6)', false, 30],
                'g_email_flood'          => ['SMALLINT(6)', false, 60],
                'g_report_flood'         => ['SMALLINT(6)', false, 60],
                'g_deledit_interval'     => ['INT(10)', false, 0],
                'g_force_merge_interval' => ['INT(10)', false, 2592000],
                'g_pm'                   => ['TINYINT(1)', false, 1],
                'g_pm_limit'             => ['INT(10) UNSIGNED', false, 100],
                'g_sig_length'           => ['SMALLINT UNSIGNED', false, 400],
                'g_sig_lines'            => ['TINYINT UNSIGNED', false, 4],
                'g_up_ext'               => ['VARCHAR(255)', false, 'webp,jpg,jpeg,png,gif,avif'],
                'g_up_size_kb'           => ['INT(10) UNSIGNED', false, 0],
                'g_up_limit_mb'          => ['INT(10) UNSIGNED', false, 0],
                'g_use_reaction'         => ['TINYINT(1)', false, 1],
                'g_use_about_me'         => ['TINYINT(1)', false, 1],
                'g_premoderation'        => ['TINYINT(1)', false, 0],
            ],
            'PRIMARY KEY' => ['g_id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::groups', $schema);

        // online
        $schema = [
            'FIELDS' => [
                'user_id'     => ['INT(10) UNSIGNED', false, 0],
                'ident'       => ['VARCHAR(45)', false, ''],
                'logged'      => ['INT(10) UNSIGNED', false, 0],
                'last_post'   => ['INT(10) UNSIGNED', false, 0],
                'last_search' => ['INT(10) UNSIGNED', false, 0],
                'o_position'  => ['VARCHAR(100)', false, ''],
                'o_name'      => ['VARCHAR(190)', false, ''],
                'o_misc'      => ['INT(10) UNSIGNED', false, 0],
            ],
            'UNIQUE KEYS' => [
                'user_id_ident_idx' => ['user_id', 'ident'],
            ],
            'INDEXES' => [
                'logged_idx' => ['logged'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::online', $schema);

        // posts
        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'poster'       => ['VARCHAR(190)', false, ''],
                'poster_id'    => ['INT(10) UNSIGNED', false, 0],
                'poster_ip'    => ['VARCHAR(45)', false, ''],
                'poster_email' => ['VARCHAR(190)', false, ''],
                'message'      => ['MEDIUMTEXT', false],
                'hide_smilies' => ['TINYINT(1)', false, 0],
                'edit_post'    => ['TINYINT(1)', false, 0],
                'posted'       => ['INT(10) UNSIGNED', false, 0],
                'edited'       => ['INT(10) UNSIGNED', false, 0],
                'editor'       => ['VARCHAR(190)', false, ''],
                'editor_id'    => ['INT(10) UNSIGNED', false, 0],
                'user_agent'   => ['VARCHAR(255)', false, ''],
                'topic_id'     => ['INT(10) UNSIGNED', false, 0],
                'reactions'    => ['VARCHAR(255)', false, ''],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'topic_id_idx'  => ['topic_id'],
                'multi_idx'     => ['poster_id', 'topic_id', 'posted'],
                'editor_id_idx' => ['editor_id'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::posts', $schema);

        // drafts
        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'poster_id'    => ['INT(10) UNSIGNED', false, 0],
                'topic_id'     => ['INT(10) UNSIGNED', false, 0],
                'forum_id'     => ['INT(10) UNSIGNED', false, 0],
                'poster_ip'    => ['VARCHAR(45)', false, ''],
                'subject'      => ['VARCHAR(255)', false, ''],
                'message'      => ['MEDIUMTEXT', false],
                'hide_smilies' => ['TINYINT(1)', false, 0],
                'pre_mod'      => ['TINYINT(1)', false, 0],
                'user_agent'   => ['VARCHAR(255)', false, ''],
                'form_data'    => ['MEDIUMTEXT', false],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'poster_id_idx' => ['poster_id'],
                'multi1_idx'    => ['topic_id', 'poster_id'],
                'multi2_idx'    => ['forum_id', 'poster_id'],
                'pre_mod_idx'   => ['pre_mod'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::drafts', $schema);

        // reactions
        $schema = [
            'FIELDS' => [
                'pid'      => ['INT(10) UNSIGNED', false, 0],
                'uid'      => ['INT(10) UNSIGNED', false, 0],
                'reaction' => ['TINYINT UNSIGNED', false, 0],
            ],
            'UNIQUE KEYS' => [
                'pid_uid_idx' => ['pid', 'uid'],
            ],
            'INDEXES' => [
                'uid_idx' => ['uid'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::reactions', $schema);

        // reports
        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'post_id'     => ['INT(10) UNSIGNED', false, 0],
                'topic_id'    => ['INT(10) UNSIGNED', false, 0],
                'forum_id'    => ['INT(10) UNSIGNED', false, 0],
                'reported_by' => ['INT(10) UNSIGNED', false, 0],
                'created'     => ['INT(10) UNSIGNED', false, 0],
                'message'     => ['TEXT', false],
                'zapped'      => ['INT(10) UNSIGNED', false, 0],
                'zapped_by'   => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'zapped_idx' => ['zapped'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::reports', $schema);

        // search_cache
        $schema = [
            'FIELDS' => [
                'search_data' => ['MEDIUMTEXT', false],
                'search_time' => ['INT(10) UNSIGNED', false, 0],
                'search_key'  => ['VARCHAR(190)', false, '', 'bin'],
            ],
            'INDEXES' => [
                'search_time_idx' => ['search_time'],
                'search_key_idx'  => ['search_key'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::search_cache', $schema);

        // search_matches
        $schema = [
            'FIELDS' => [
                'post_id'       => ['INT(10) UNSIGNED', false, 0],
                'word_id'       => ['INT(10) UNSIGNED', false, 0],
                'subject_match' => ['TINYINT(1)', false, 0],
            ],
            'INDEXES' => [
                'multi_idx' => ['word_id', 'post_id'],
                'post_id_idx' => ['post_id'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::search_matches', $schema);

        // search_words
        $schema = [
            'FIELDS' => [
                'id'   => ['SERIAL', false],
                'word' => ['VARCHAR(20)', false, '' , 'bin'],
            ],
            'PRIMARY KEY' => ['id'],
            'UNIQUE KEYS' => [
                'word_idx' => ['word']
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::search_words', $schema);

        // topic_subscriptions
        $schema = [
            'FIELDS' => [
                'user_id'  => ['INT(10) UNSIGNED', false, 0],
                'topic_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['user_id', 'topic_id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::topic_subscriptions', $schema);

        // forum_subscriptions
        $schema = [
            'FIELDS' => [
                'user_id'  => ['INT(10) UNSIGNED', false, 0],
                'forum_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['user_id', 'forum_id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::forum_subscriptions', $schema);

        // topics
        $schema = [
            'FIELDS' => [
                'id'             => ['SERIAL', false],
                'poster'         => ['VARCHAR(190)', false, ''],
                'poster_id'      => ['INT(10) UNSIGNED', false, 0],
                'subject'        => ['VARCHAR(255)', false, ''],
                'posted'         => ['INT(10) UNSIGNED', false, 0],
                'first_post_id'  => ['INT(10) UNSIGNED', false, 0],
                'last_post'      => ['INT(10) UNSIGNED', false, 0],
                'last_post_id'   => ['INT(10) UNSIGNED', false, 0],
                'last_poster'    => ['VARCHAR(190)', false, ''],
                'last_poster_id' => ['INT(10) UNSIGNED', false, 0],
                'num_views'      => ['INT(10) UNSIGNED', false, 0],
                'num_replies'    => ['INT(10) UNSIGNED', false, 0],
                'closed'         => ['TINYINT(1)', false, 0],
                'sticky'         => ['TINYINT(1)', false, 0],
                'stick_fp'       => ['TINYINT(1)', false, 0],
                'moved_to'       => ['INT(10) UNSIGNED', false, 0],
                'forum_id'       => ['INT(10) UNSIGNED', false, 0],
                'poll_type'      => ['SMALLINT UNSIGNED', false, 0],
                'poll_time'      => ['INT(10) UNSIGNED', false, 0],
                'poll_term'      => ['TINYINT', false, 0],
                'solution'       => ['INT(10) UNSIGNED', false, 0],
                'solution_wa'    => ['VARCHAR(190)', false, ''],
                'solution_wa_id' => ['INT(10) UNSIGNED', false, 0],
                'solution_time'  => ['INT(10) UNSIGNED', false, 0],
                'toc'            => ['TEXT', true],
                'cf_level'       => ['TINYINT UNSIGNED', false, 0],
                'cf_data'        => ['TEXT', true],
                'premoderation'  => ['TINYINT', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'multi_2_idx'       => ['forum_id', 'sticky', 'last_post'],
                'last_post_idx'     => ['last_post'],
                'first_post_id_idx' => ['first_post_id'],
                'multi_1_idx'       => ['moved_to', 'forum_id', 'num_replies', 'last_post'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::topics', $schema);

        // pm_block
        $schema = [
            'FIELDS' => [
                'bl_first_id'  => ['INT(10) UNSIGNED', false, 0],
                'bl_second_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'INDEXES' => [
                'bl_first_id_idx'  => ['bl_first_id'],
                'bl_second_id_idx' => ['bl_second_id'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::pm_block', $schema);

        // pm_posts
        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'poster'       => ['VARCHAR(190)', false, ''],
                'poster_id'    => ['INT(10) UNSIGNED', false, 0],
                'poster_ip'    => ['VARCHAR(45)', false, ''],
                'message'      => ['TEXT', false],
                'hide_smilies' => ['TINYINT(1)', false, 0],
                'posted'       => ['INT(10) UNSIGNED', false, 0],
                'edited'       => ['INT(10) UNSIGNED', false, 0],
                'topic_id'     => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'topic_id_idx' => ['topic_id'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::pm_posts', $schema);

        // pm_topics
        $schema = [
            'FIELDS' => [
                'id'            => ['SERIAL', false],
                'subject'       => ['VARCHAR(255)', false, ''],
                'poster'        => ['VARCHAR(190)', false, ''],
                'poster_id'     => ['INT(10) UNSIGNED', false, 0],
                'poster_status' => ['TINYINT UNSIGNED', false, 0],
                'poster_visit'  => ['INT(10) UNSIGNED', false, 0],
                'target'        => ['VARCHAR(190)', false, ''],
                'target_id'     => ['INT(10) UNSIGNED', false, 0],
                'target_status' => ['TINYINT UNSIGNED', false, 0],
                'target_visit'  => ['INT(10) UNSIGNED', false, 0],
                'num_replies'   => ['INT(10) UNSIGNED', false, 0],
                'first_post_id' => ['INT(10) UNSIGNED', false, 0],
                'last_post'     => ['INT(10) UNSIGNED', false, 0],
                'last_post_id'  => ['INT(10) UNSIGNED', false, 0],
                'last_number'   => ['TINYINT UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'last_post_idx'        => ['last_post'],
                'poster_id_status_idx' => ['poster_id', 'poster_status'],
                'target_id_status_idx' => ['target_id', 'target_status'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::pm_topics', $schema);

        // users
        $schema = [
            'FIELDS' => [
                'id'               => ['SERIAL', false],
                'group_id'         => ['INT(10) UNSIGNED', false, 0],
                'username'         => ['VARCHAR(190)', false, ''],
                'username_normal'  => ['VARCHAR(190)', false, ''],
                'password'         => ['VARCHAR(255)', false, ''],
                'email'            => ['VARCHAR(190)', false, ''],
                'email_normal'     => ['VARCHAR(190)', false, ''],
                'email_confirmed'  => ['TINYINT(1)', false, 0],
                'title'            => ['VARCHAR(50)', false, ''],
                'avatar'           => ['VARCHAR(30)', false, ''],
                'realname'         => ['VARCHAR(40)', false, ''],
                'url'              => ['VARCHAR(100)', false, ''],
                'sn_profile1'      => ['VARCHAR(255)', false, ''],
                'sn_profile2'      => ['VARCHAR(255)', false, ''],
                'sn_profile3'      => ['VARCHAR(255)', false, ''],
                'sn_profile4'      => ['VARCHAR(255)', false, ''],
                'sn_profile5'      => ['VARCHAR(255)', false, ''],
                'location'         => ['VARCHAR(30)', false, ''],
                'signature'        => ['TEXT', false],
                'disp_topics'      => ['TINYINT UNSIGNED', false, 0],
                'disp_posts'       => ['TINYINT UNSIGNED', false, 0],
                'email_setting'    => ['TINYINT(1)', false, 1],
                'notify_with_post' => ['TINYINT(1)', false, 0],
                'auto_notify'      => ['TINYINT(1)', false, 0],
                'show_smilies'     => ['TINYINT(1)', false, 1],
                'show_img'         => ['TINYINT(1)', false, 1],
                'show_img_sig'     => ['TINYINT(1)', false, 1],
                'show_avatars'     => ['TINYINT(1)', false, 1],
                'show_sig'         => ['TINYINT(1)', false, 1],
                'timezone'         => ['VARCHAR(255)', false, \date_default_timezone_get()],
                'time_format'      => ['TINYINT(1)', false, 1],
                'date_format'      => ['TINYINT(1)', false, 1],
                'language'         => ['VARCHAR(25)', false, ''],
                'locale'           => ['VARCHAR(20)', false, 'en'],
                'style'            => ['VARCHAR(25)', false, ''],
                'num_posts'        => ['INT(10) UNSIGNED', false, 0],
                'num_topics'       => ['INT(10) UNSIGNED', false, 0],
                'num_drafts'       => ['INT(10) UNSIGNED', false, 0],
                'last_post'        => ['INT(10) UNSIGNED', false, 0],
                'last_search'      => ['INT(10) UNSIGNED', false, 0],
                'last_email_sent'  => ['INT(10) UNSIGNED', false, 0],
                'last_report_sent' => ['INT(10) UNSIGNED', false, 0],
                'registered'       => ['INT(10) UNSIGNED', false, 0],
                'registration_ip'  => ['VARCHAR(45)', false, ''],
                'last_visit'       => ['INT(10) UNSIGNED', false, 0],
                'admin_note'       => ['VARCHAR(30)', false, ''],
                'activate_string'  => ['VARCHAR(80)', false, ''],
                'u_pm'             => ['TINYINT(1)', false, 1],
                'u_pm_notify'      => ['TINYINT(1)', false, 0],
                'u_pm_flash'       => ['TINYINT(1)', false, 0],
                'u_pm_num_new'     => ['INT(10) UNSIGNED', false, 0],
                'u_pm_num_all'     => ['INT(10) UNSIGNED', false, 0],
                'u_pm_last_post'   => ['INT(10) UNSIGNED', false, 0],
                'warning_flag'     => ['TINYINT(1)', false, 0],
                'warning_all'      => ['INT(10) UNSIGNED', false, 0],
                'gender'           => ['TINYINT UNSIGNED', false, FORK_GEN_NOT],
                'u_mark_all_read'  => ['INT(10) UNSIGNED', false, 0],
                'last_report_id'   => ['INT(10) UNSIGNED', false, 0],
                'ip_check_type'    => ['TINYINT UNSIGNED', false, 0],
                'login_ip_cache'   => ['VARCHAR(255)', false, ''],
                'u_up_size_mb'     => ['INT(10) UNSIGNED', false, 0],
                'unfollowed_f'     => ['VARCHAR(255)', false, ''],
                'show_reaction'    => ['TINYINT(1)', false, 1],
                'page_scroll'      => ['TINYINT', false, 0],
                'about_me_id'      => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'UNIQUE KEYS' => [
                'username_idx'     => ['username(25)'],
                'email_normal_idx' => ['email_normal'],
            ],
            'INDEXES' => [
                'registered_idx' => ['registered'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::users', $schema);

        // smilies
        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'sm_image'    => ['VARCHAR(40)', false, ''],
                'sm_code'     => ['VARCHAR(20)', false, ''],
                'sm_position' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::smilies', $schema);

        // warnings
        $schema = [
            'FIELDS' => [
                'id'        => ['SERIAL', false],
                'poster'    => ['VARCHAR(190)', false, ''],
                'poster_id' => ['INT(10) UNSIGNED', false, 0],
                'posted'    => ['INT(10) UNSIGNED', false, 0],
                'message'   => ['TEXT', false],
            ],
            'PRIMARY KEY' => ['id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::warnings', $schema);

        // poll
        $schema = [
            'FIELDS' => [
                'tid'         => ['INT(10) UNSIGNED', false, 0],
                'question_id' => ['TINYINT', false, 0],
                'field_id'    => ['TINYINT', false, 0],
                'qna_text'    => ['VARCHAR(255)', false, ''],
                'votes'       => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['tid', 'question_id', 'field_id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::poll', $schema);

        // poll_voted
        $schema = [
            'FIELDS' => [
                'tid' => ['INT(10) UNSIGNED', false],
                'uid' => ['INT(10) UNSIGNED', false],
                'rez' => ['TEXT', false],
            ],
            'PRIMARY KEY' => ['tid', 'uid'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::poll_voted', $schema) ;

        // mark_of_forum
        $schema = [
            'FIELDS' => [
                'uid'              => ['INT(10) UNSIGNED', false, 0],
                'fid'              => ['INT(10) UNSIGNED', false, 0],
                'mf_mark_all_read' => ['INT(10) UNSIGNED', false, 0],
            ],
            'UNIQUE KEYS' => [
                'uid_fid_idx' => ['uid', 'fid'],
            ],
            'INDEXES' => [
                'mf_mark_all_read_idx' => ['mf_mark_all_read'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::mark_of_forum', $schema);

        // mark_of_topic
        $schema = [
            'FIELDS' => [
                'uid'           => ['INT(10) UNSIGNED', false, 0],
                'tid'           => ['INT(10) UNSIGNED', false, 0],
                'mt_last_visit' => ['INT(10) UNSIGNED', false, 0],
                'mt_last_read'  => ['INT(10) UNSIGNED', false, 0],
            ],
            'UNIQUE KEYS' => [
                'uid_tid_idx' => ['uid', 'tid'],
            ],
            'INDEXES' => [
                'mt_last_visit_idx' => ['mt_last_visit'],
                'mt_last_read_idx'  => ['mt_last_read'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::mark_of_topic', $schema);

        // providers
        $schema = [
            'FIELDS' => [
                'pr_name'     => ['VARCHAR(25)', false],
                'pr_allow'    => ['TINYINT(1)', false, 0],
                'pr_pos'      => ['INT(10) UNSIGNED', false, 0],
                'pr_cl_id'    => ['VARCHAR(255)', false, ''],
                'pr_cl_sec'   => ['VARCHAR(255)', false, ''],
            ],
            'UNIQUE KEYS' => [
                'pr_name_idx' => ['pr_name'],
            ],
        ];
        $this->c->DB->createTable('::providers', $schema);

        // providers_users
        $schema = [
            'FIELDS' => [
                'uid'               => ['INT(10) UNSIGNED', false],
                'pr_name'           => ['VARCHAR(25)', false],
                'pu_uid'            => ['VARCHAR(165)', false],
                'pu_email'          => ['VARCHAR(190)', false, ''],
                'pu_email_normal'   => ['VARCHAR(190)', false, ''],
                'pu_email_verified' => ['TINYINT(1)', false, 0],
            ],
            'UNIQUE KEYS' => [
                'pr_name_pu_uid_idx' => ['pr_name', 'pu_uid'],
            ],
            'INDEXES' => [
                'uid_idx'             => ['uid'],
                'pu_email_normal_idx' => ['pu_email_normal'],
            ],
        ];
        $this->c->DB->createTable('::providers_users', $schema);

        // заполнение providers
        $providers = [
            'github', 'yandex', 'google',
        ];

        $query = 'INSERT INTO ::providers (pr_name, pr_pos)
            VALUES (?s:name, ?i:pos)';

        foreach ($providers as $pos => $name) {
            $vars = [
                ':name' => $name,
                ':pos'  => $pos,
            ];

            $this->c->DB->exec($query, $vars);
        }

        // заполнение groups
        $now    = \time();
        $groups = [
            [
                'g_id'                   => FORK_GROUP_ADMIN,
                'g_title'                => __('Administrators'),
                'g_user_title'           => __('Administrator '),
                'g_mod_promote_users'    => 1,
                'g_read_board'           => 1,
                'g_view_users'           => 1,
                'g_post_replies'         => 1,
                'g_post_topics'          => 1,
                'g_edit_posts'           => 1,
                'g_delete_posts'         => 1,
                'g_delete_topics'        => 1,
                'g_post_links'           => 1,
                'g_set_title'            => 1,
                'g_search'               => 1,
                'g_search_users'         => 1,
                'g_send_email'           => 1,
                'g_post_flood'           => 0,
                'g_search_flood'         => 0,
                'g_email_flood'          => 0,
                'g_report_flood'         => 0,
                'g_pm_limit'             => 0,
                'g_sig_length'           => 10000,
                'g_sig_lines'            => 255,
            ],
            [
                'g_id'                   => FORK_GROUP_MOD,
                'g_title'                => __('Moderators'),
                'g_user_title'           => __('Moderator '),
                'g_moderator'            => 1,
                'g_mod_edit_users'       => 1,
                'g_mod_rename_users'     => 1,
                'g_mod_change_passwords' => 1,
                'g_mod_ban_users'        => 1,
                'g_mod_promote_users'    => 1,
                'g_read_board'           => 1,
                'g_view_users'           => 1,
                'g_post_replies'         => 1,
                'g_post_topics'          => 1,
                'g_edit_posts'           => 1,
                'g_delete_posts'         => 1,
                'g_delete_topics'        => 1,
                'g_post_links'           => 1,
                'g_set_title'            => 1,
                'g_search'               => 1,
                'g_search_users'         => 1,
                'g_send_email'           => 1,
                'g_post_flood'           => 0,
                'g_search_flood'         => 0,
                'g_email_flood'          => 0,
                'g_report_flood'         => 0,
            ],
            [
                'g_id'                   => FORK_GROUP_GUEST,
                'g_title'                => __('Guests'),
                'g_user_title'           => '',
                'g_read_board'           => 1,
                'g_view_users'           => 1,
                'g_post_replies'         => 0,
                'g_post_topics'          => 0,
                'g_edit_posts'           => 0,
                'g_delete_posts'         => 0,
                'g_delete_topics'        => 0,
                'g_post_links'           => 0,
                'g_set_title'            => 0,
                'g_search'               => 1,
                'g_search_users'         => 1,
                'g_send_email'           => 0,
                'g_post_flood'           => 120,
                'g_search_flood'         => 60,
                'g_email_flood'          => 0,
                'g_report_flood'         => 0,
                'g_pm'                   => 0,
                'g_sig_length'           => 0,
                'g_sig_lines'            => 0,
                'g_use_reaction'         => 0,
            ],
            [
                'g_id'                   => FORK_GROUP_MEMBER,
                'g_title'                => __('Members'),
                'g_user_title'           => '',
                'g_read_board'           => 1,
                'g_view_users'           => 1,
                'g_post_replies'         => 1,
                'g_post_topics'          => 1,
                'g_edit_posts'           => 1,
                'g_delete_posts'         => 1,
                'g_delete_topics'        => 1,
                'g_post_links'           => 1,
                'g_set_title'            => 0,
                'g_search'               => 1,
                'g_search_users'         => 1,
                'g_send_email'           => 1,
                'g_post_flood'           => 30,
                'g_search_flood'         => 30,
                'g_email_flood'          => 60,
                'g_report_flood'         => 60,
            ],
            [
                'g_id'                   => FORK_GROUP_NEW_MEMBER,
                'g_title'                => __('New members'),
                'g_user_title'           => __('New member'),
                'g_read_board'           => 1,
                'g_view_users'           => 1,
                'g_post_replies'         => 1,
                'g_post_topics'          => 1,
                'g_edit_posts'           => 1,
                'g_delete_posts'         => 1,
                'g_delete_topics'        => 1,
                'g_post_links'           => 0,
                'g_set_title'            => 0,
                'g_search'               => 1,
                'g_search_users'         => 1,
                'g_send_email'           => 1,
                'g_post_flood'           => 60,
                'g_search_flood'         => 30,
                'g_email_flood'          => 120,
                'g_report_flood'         => 60,
                'g_deledit_interval'     => 600,
                'g_pm'                   => 0,
                'g_promote_min_posts'    => 5,
                'g_promote_next_group'   => FORK_GROUP_MEMBER,
                'g_use_reaction'         => 0,
            ],
        ];

        foreach ($groups as $group) {

            $fields = [];
            $values = [];

            foreach ($group as $key => $value) {
                $fields[] = $key;
                $values[] = (\is_int($value) ? '?i:' : '?s:') . $key;
            }

            $fields = \implode(', ', $fields);
            $values = \implode(', ', $values);
            $query  = "INSERT INTO ::groups ({$fields}) VALUES ({$values})";

            $this->c->DB->exec($query, $group);
        }

        if ('pgsql' === $v->dbtype) {
            $this->c->DB->exec('ALTER SEQUENCE ::groups_g_id_seq RESTART WITH ' . (FORK_GROUP_NEW_MEMBER + 1));
        }

        // заполнение config
        $forkConfig = [
            'i_fork_revision'         => FORK_REVISION,
            'o_board_title'           => $v->title,
            'o_board_desc'            => $v->descr,
            'o_default_timezone'      => \date_default_timezone_get(),
            'i_timeout_visit'         => 3600,
            'i_timeout_online'        => 900,
            'i_redirect_delay'        => 1,
            'b_show_user_info'        => 1,
            'b_show_post_count'       => 1,
            'b_smilies'               => 1,
            'b_smilies_sig'           => 1,
            'b_make_links'            => 1,
            'o_default_lang'          => $v->defaultlang,
            'b_default_lang_auto'     => 1,
            'o_default_style'         => $v->defaultstyle,
            'i_default_user_group'    => FORK_GROUP_NEW_MEMBER,
            'i_topic_review'          => 15,
            'i_disp_topics_default'   => 30,
            'i_disp_posts_default'    => 25,
            'i_disp_users'            => 50,
            'b_quickpost'             => 1,
            'b_users_online'          => 1,
            'b_censoring'             => 0,
            'i_censoring_count'       => 0,
            'b_show_dot'              => 0,
            'b_topic_views'           => 1,
            'o_additional_navlinks'   => '',
            'i_report_method'         => 0,
            'b_regs_report'           => 0,
            'i_default_email_setting' => 2,
            'o_mailing_list'          => $v->email,
            'b_avatars'               => \filter_var(\ini_get('file_uploads'), \FILTER_VALIDATE_BOOL) ? 1 : 0,
            'o_avatars_dir'           => '/img/avatars',
            'i_avatars_width'         => 160,
            'i_avatars_height'        => 160,
            'i_avatars_size'          => 51200,
            'i_avatars_quality'       => 75,
            'o_admin_email'           => $v->email,
            'o_webmaster_email'       => $v->email,
            'b_forum_subscriptions'   => 1,
            'b_topic_subscriptions'   => 1,
            'i_email_max_recipients'  => 1,
            'o_smtp_host'             => '',
            'o_smtp_user'             => '',
            'o_smtp_pass'             => '',
            'b_smtp_ssl'              => 0,
            'b_email_use_cron'        => 0,
            'b_regs_allow'            => 1,
            'b_regs_verify'           => 1,
            'b_regs_disable_email'    => 0,
            'b_oauth_allow'           => 0,
            'b_announcement'          => 0,
            'o_announcement_message'  => __('Announcement '),
            'b_rules'                 => 0,
            'o_rules_message'         => __('Rules '),
            'b_maintenance'           => 0,
            'o_maintenance_message'   => __('Maintenance message '),
            'i_feed_type'             => 2,
            'i_feed_ttl'              => 0,
            'b_message_bbcode'        => 1,
            'b_message_all_caps'      => 0,
            'b_subject_all_caps'      => 0,
            'b_sig_all_caps'          => 0,
            'b_sig_bbcode'            => 1,
            'b_force_guest_email'     => 1,
            'b_hide_guest_email_fld'  => 0,
            'b_pm'                    => 0,
            'b_poll_enabled'          => 0,
            'i_poll_max_questions'    => 3,
            'i_poll_max_fields'       => 20,
            'i_poll_time'             => 60,
            'i_poll_term'             => 3,
            'b_poll_guest'            => 0,
            'a_max_users'             => \json_encode(['number' => 1, 'time' => \time()], FORK_JSON_ENCODE),
            'a_bb_white_mes'          => \json_encode([], FORK_JSON_ENCODE),
            'a_bb_white_sig'          => \json_encode(['b', 'i', 'u', 'color', 'colour', 'email', 'url'], FORK_JSON_ENCODE),
            'a_bb_black_mes'          => \json_encode([], FORK_JSON_ENCODE),
            'a_bb_black_sig'          => \json_encode([], FORK_JSON_ENCODE),
            'a_guest_set'             => \json_encode(
                [
                    'show_smilies' => 1,
                    'show_sig'     => 1,
                    'show_avatars' => 1,
                    'show_img'     => 1,
                    'show_img_sig' => 1,
                ], FORK_JSON_ENCODE
            ),
            's_РЕГИСТР'               => 'Ok',
            'b_upload'                => 0,
            'i_upload_img_quality'    => 75,
            'i_upload_img_axis_limit' => 1920,
            's_upload_img_outf'       => 'webp,jpg,png,gif',
            'i_search_ttl'            => 900,
            'b_ant_hidden_ch'         => 1,
            'b_ant_use_js'            => 0,
            's_meta_desc'             => '',
            'a_og_image'              => \json_encode([], FORK_JSON_ENCODE),
            'b_reaction'              => 0,
            'a_reaction_types'        => \json_encode(
                [
                    1  => ['like', true],
                    2  => ['fire', true],
                    3  => ['lol', true],
                    4  => ['smile', true],
                    5  => ['frown', true],
                    6  => ['sad', true],
                    7  => ['cry', true],
                    8  => ['angry', true],
                    9  => ['dislike', true],
                    10 => ['meh', true],
                    11 => ['shock', true],
                ], FORK_JSON_ENCODE
            ),
            'b_show_user_reaction'    => 0,
            'i_about_me_topic_id'     => 0,
            'b_premoderation'         => 0,
        ];

        foreach ($forkConfig as $name => $value) {
            $this->c->DB->exec('INSERT INTO ::config (conf_name, conf_value) VALUES (?s, ?s)', [$name, $value]);
        }

        // заполнение users, categories, forums, topics, posts
        $topicId = 1;

        $this->c->DB->exec('INSERT INTO ::users (group_id, username, username_normal, password, email, email_normal, language, style, num_posts, last_post, registered, registration_ip, last_visit, signature, num_topics) VALUES (?i, ?s, ?s, ?s, ?s, ?s, ?s, ?s, 1, ?i, ?i, ?s, ?i, \'\', 1)', [FORK_GROUP_ADMIN, $v->username, $this->c->users->normUsername($v->username), \password_hash($v->password, \PASSWORD_DEFAULT), $v->email, $this->c->NormEmail->normalize($v->email), $v->defaultlang, $v->defaultstyle, $now, $now, FORK_ADDR, $now]);

        $adminId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::categories (cat_name, disp_position) VALUES (?s, ?i)', [__('Test category'), 1]);

        $catId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES(?s, ?i, ?s, ?s, ?i, ?i)', [$v->username, $adminId, FORK_ADDR, __('Test message'), $now, $topicId]);

        $postId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::forums (forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, last_poster_id, last_topic, disp_position, cat_id, moderators) VALUES (?s, ?s, ?i, ?i, ?i, ?i, ?s, ?i, ?s, ?i, ?i, \'\')', [__('Test forum'), __('This is just a test forum'), 1, 1, $now, $postId, $v->username, $adminId, __('Test post'), 1, $catId]);

        $forumId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::topics (poster, poster_id, subject, posted, first_post_id, last_post, last_post_id, last_poster, last_poster_id, forum_id) VALUES(?s, ?i, ?s, ?i, ?i, ?i, ?i, ?s, ?i, ?i)', [$v->username, $adminId, __('Test post'), $now, $postId, $now, $postId, $v->username, $adminId, $forumId]);

        $topicId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('UPDATE ::posts SET topic_id=?i WHERE id=?i', [$topicId, $postId]);

        // заполнение для "Обо мне"
        $topicId = 1;

        $this->c->DB->exec('INSERT INTO ::posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES(?s, ?i, ?s, ?s, ?i, ?i)', ['ForkBB', 0, FORK_ADDR, 'Start', $now, $topicId]);

        $postId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::topics (poster, poster_id, subject, posted, first_post_id, last_post, last_post_id, last_poster, last_poster_id, forum_id) VALUES(?s, ?i, ?s, ?i, ?i, ?i, ?i, ?s, ?i, ?i)', ['ForkBB', 0, '[system] About me', $now, $postId, $now, $postId, 'ForkBB', 0, FORK_SFID]);

        $topicId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('UPDATE ::posts SET topic_id=?i WHERE id=?i', [$topicId, $postId]);

        $this->c->DB->exec('UPDATE ::config SET conf_value=?s WHERE conf_name=?s', [$topicId, 'i_about_me_topic_id']);

        // заполнение smilies
        $smilies = [
            ':)'         => 'smile.png',
            '=)'         => 'smile.png',
            ':|'         => 'neutral.png',
            '=|'         => 'neutral.png',
            ':('         => 'sad.png',
            '=('         => 'sad.png',
            ':D'         => 'big_smile.png',
            '=D'         => 'big_smile.png',
            ':o'         => 'yikes.png',
            ':O'         => 'yikes.png',
            ';)'         => 'wink.png',
            ':/'         => 'hmm.png',
            ':P'         => 'tongue.png',
            ':p'         => 'tongue.png',
            ':lol:'      => 'lol.png',
            ':mad:'      => 'mad.png',
            ':rolleyes:' => 'roll.png',
            ':cool:'     => 'cool.png',
        ];
        $i = 0;

        foreach ($smilies as $text => $img) {
            $this->c->DB->exec('INSERT INTO ::smilies (sm_image, sm_code, sm_position) VALUES(?s, ?s, ?i)', [$img, $text, $i++]); //????
        }

        // заполнение bbcodes
        $query   = 'INSERT INTO ::bbcode (bb_tag, bb_edit, bb_delete, bb_structure)
            VALUES(?s:tag, 1, 0, ?s:structure)';
        $bbcodes = include $this->c->DIR_CONFIG . '/defaultBBCode.php';

        foreach ($bbcodes as $bbcode) {
            $vars = [
                ':tag'       => $bbcode['tag'],
                ':structure' => \json_encode($bbcode, FORK_JSON_ENCODE),
            ];

            $this->c->DB->exec($query, $vars);
        }

        $config = \file_get_contents($this->c->DIR_CONFIG . '/main.dist.php');

        if (false === $config) {
            throw new RuntimeException('No access to main.dist.php.');
        }

        $repl = [ //????
            '_BASE_URL_'      => $v->baseurl,
            '_DB_DSN_'        => $this->c->DB_DSN,
            '_DB_USERNAME_'   => $this->c->DB_USERNAME,
            '_DB_PASSWORD_'   => $this->c->DB_PASSWORD,
            '_DB_PREFIX_'     => $this->c->DB_PREFIX,
            '_SALT_FOR_HMAC_' => $this->c->Secury->randomPass(\mt_rand(20,30)),
            '_COOKIE_PREFIX_' => 'fork' . $this->c->Secury->randomHash(7) . '_',
            '_COOKIE_DOMAIN_' => $v->cookie_domain,
            '_COOKIE_PATH_'   => $v->cookie_path,
            '_COOKIE_SECURE_' => 1 === $v->cookie_secure ? 'true' : 'false',
            '_COOKIE_KEY1_'   => $this->c->Secury->randomPass(\mt_rand(20,30)),
            '_COOKIE_KEY2_'   => $this->c->Secury->randomPass(\mt_rand(20,30)),
        ];

        foreach ($repl as $key => $val) {
            $config = \str_replace($key, \addslashes($val), $config);
        }

        $config = \str_replace('_DB_OPTIONS_', $this->c->DB_OPTS_AS_STR, $config);
        $result = \file_put_contents($this->c->DIR_CONFIG . '/main.php', $config);

        if (false === $result) {
            throw new RuntimeException('No write to main.php');
        }

        return $this->c->Redirect->toIndex();
    }
}
