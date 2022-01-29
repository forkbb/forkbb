<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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
    const PHP_MIN    = '7.3.0';
    const MYSQL_MIN  = '5.5.3';
    const SQLITE_MIN = '3.25.0';

    const JSON_OPTIONS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;

    /**
     * Для MySQL
     * @var string
     */
    protected $DBEngine = '';

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
            $this->fIswev = ['e', ['You are running error', 'PHP', \PHP_VERSION, $this->c->FORK_REVISION, self::PHP_MIN]];
        }

        // типы БД
        $this->dbTypes = $this->DBTypes();

        if (empty($this->dbTypes)) {
            $this->fIswev = ['e', 'No DB extensions'];
        }

        // доступность папок на запись
        $folders = [
            $this->c->DIR_APP . '/config',
            $this->c->DIR_APP . '/config/db',
            $this->c->DIR_CACHE,
            $this->c->DIR_PUBLIC . '/img/avatars',
        ];

        foreach ($folders as $folder) {
            if (! \is_writable($folder)) {
                $folder       = \str_replace(\dirname($this->c->DIR_APP), '', $folder);
                $this->fIswev = ['e', ['Alert folder', $folder]];
            }
        }

        // доступность шаблона конфигурации
        $config = \file_get_contents($this->c->DIR_APP . '/config/main.dist.php');

        if (false === $config) {
            $this->fIswev = ['e', 'No access to main.dist.php'];
        }

        unset($config);

        // языки
        $langs = $this->c->Func->getNameLangs();

        if (empty($langs)) {
            $this->fIswev = ['e', 'No language packs'];
        }

        // стили
        $styles = $this->c->Func->getStyles();

        if (empty($styles)) {
            $this->fIswev = ['e', 'No styles'];
        }

        if (
            'POST' === $method
            && ! $changeLang
            && empty($this->fIswev['e'])
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
                    'defaultlang'   => 'required|string:trim|in:' . \implode(',', $this->c->Func->getLangs()),
                    'defaultstyle'  => 'required|string:trim|in:' . \implode(',', $this->c->Func->getStyles()),
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
                    'email'        => 'Wrong email',
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
                    'info' => [
                        [
                            'value' => __('Database setup'),
                            'html'  => true,
                        ],
                        [
                            'value' => __('Info 1'),
                        ],
                    ],
                ],
                'db' => [
                    'fields' => [
                        'dbtype' => [
                            'type'     => 'select',
                            'options'  => $this->dbTypes,
                            'value'    => $v ? $v->dbtype : 'mysql_innodb',
                            'caption'  => 'Database type',
                            'help'     => 'Info 2',
                        ],
                        'dbhost' => [
                            'type'     => 'text',
                            'value'    => $v ? $v->dbhost : 'localhost',
                            'caption'  => 'Database server hostname',
                            'help'     => 'Info 3',
                            'required' => true,
                        ],
                        'dbname' => [
                            'type'     => 'text',
                            'value'    => $v ? $v->dbname : '',
                            'caption'  => 'Database name',
                            'help'     => 'Info 4',
                            'required' => true,
                        ],
                        'dbuser' => [
                            'type'    => 'text',
                            'value'   => $v ? $v->dbuser : '',
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
                            'value'     => $v ? $v->dbprefix : '',
                            'caption'   => 'Table prefix',
                            'help'      => 'Info 6',
                            'required' => true,
                        ],
                    ],
                ],
                'adm-info' => [
                    'info' => [
                        [
                            'value' => __('Administration setup'),
                            'html'  => true,
                        ],
                        [
                            'value' => __('Info 7'),
                        ],
                    ],
                ],
                'adm' => [
                    'fields' => [
                        'username' => [
                            'type'      => 'text',
                            'maxlength' => '25',
                            'pattern'   => '^.{2,25}$',
                            'value'     => $v ? $v->username : '',
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
                            'type'      => 'text',
                            'maxlength' => '80',
                            'pattern'   => '.+@.+',
                            'value'     => $v ? $v->email : '',
                            'caption'   => 'Administrator email',
                            'help'      => 'Info 10',
                            'required'  => true,
                        ],

                    ],
                ],
                'board-info' => [
                    'info' => [
                        [
                            'value' => __('Board setup'),
                            'html'  => true,
                        ],
                        [
                            'value' => __('Info 11'),
                        ],
                    ],
                ],
                'board' => [
                    'fields' => [
                        'title' => [
                            'type'      => 'text',
                            'maxlength' => '255',
                            'value'     => $v ? $v->title : __('My ForkBB Forum'),
                            'caption'   => 'Board title',
                            'required'  => true,
                        ],
                        'descr' => [
                            'type'      => 'text',
                            'maxlength' => '16000',
                            'value'     => $v ? $v->descr : __('Description'),
                            'caption'   => 'Board description',
                        ],
                        'baseurl' => [
                            'type'      => 'text',
                            'maxlength' => '128',
                            'value'     => $v ? $v->baseurl : $this->c->BASE_URL,
                            'caption'   => 'Base URL',
                            'required'  => true,
                        ],
                        'defaultlang' => [
                            'type'      => 'select',
                            'options'   => $langs,
                            'value'     => $v ? $v->defaultlang : $this->user->language,
                            'caption'   => 'Default language',
                        ],
                        'defaultstyle' => [
                            'type'      => 'select',
                            'options'   => $styles,
                            'value'     => $v ? $v->defaultstyle : $this->user->style,
                            'caption'   => 'Default style',
                        ],
                    ],
                ],
                'cookie-info' => [
                    'info' => [
                        [
                            'value' => __('Cookie setup'),
                            'html'  => true,
                        ],
                        [
                            'value' => __('Info 12'),
                        ],
                    ],
                ],
                'cookie' => [
                    'fields' => [
                        'cookie_domain' => [
                            'type'      => 'text',
                            'maxlength' => '128',
                            'value'     => $v ? $v->cookie_domain : '',
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

        $this->nameTpl    = 'layouts/install';
        $this->onlinePos  = null;
        $this->rev        = $this->c->FORK_REVISION;

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
        }

        if (\version_compare($version, $versionNeed, '<')) {
            $v->addError(['You are running error', $progName, $version, $this->c->FORK_REVISION, $versionNeed]);

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
                'id'              => ['SERIAL', false],
                'forum_name'      => ['VARCHAR(80)', false, 'New forum'],
                'forum_desc'      => ['TEXT', false],
                'redirect_url'    => ['VARCHAR(255)', false, ''],
                'moderators'      => ['TEXT', false],
                'num_topics'      => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'num_posts'       => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'last_post'       => ['INT(10) UNSIGNED', false, 0],
                'last_post_id'    => ['INT(10) UNSIGNED', false, 0],
                'last_poster'     => ['VARCHAR(190)', false, ''],
                'last_poster_id'  => ['INT(10) UNSIGNED', false, 0],
                'last_topic'      => ['VARCHAR(255)', false, ''],
                'sort_by'         => ['TINYINT(1)', false, 0],
                'disp_position'   => ['INT(10)', false, 0],
                'cat_id'          => ['INT(10) UNSIGNED', false, 0],
                'no_sum_mess'     => ['TINYINT(1)', false, 0],
                'parent_forum_id' => ['INT(10) UNSIGNED', false, 0],
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
                'g_pm'                   => ['TINYINT(1)', false, 1],
                'g_pm_limit'             => ['INT(10) UNSIGNED', false, 100],
                'g_sig_length'           => ['SMALLINT UNSIGNED', false, 400],
                'g_sig_lines'            => ['TINYINT UNSIGNED', false, 4],
            ],
            'PRIMARY KEY' => ['g_id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::groups', $schema);

        // online
        $schema = [
            'FIELDS' => [
                'user_id'     => ['INT(10) UNSIGNED', false, 1],
                'ident'       => ['VARCHAR(190)', false, ''],
                'logged'      => ['INT(10) UNSIGNED', false, 0],
                'last_post'   => ['INT(10) UNSIGNED', false, 0],
                'last_search' => ['INT(10) UNSIGNED', false, 0],
                'o_position'  => ['VARCHAR(100)', false, ''],
                'o_name'      => ['VARCHAR(190)', false, ''],
            ],
            'UNIQUE KEYS' => [
                'user_id_ident_idx' => ['user_id', 'ident(45)'],
            ],
            'INDEXES' => [
                'ident_idx'      => ['ident'],
                'logged_idx'     => ['logged'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::online', $schema);

        // posts
        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'poster'       => ['VARCHAR(190)', false, ''],
                'poster_id'    => ['INT(10) UNSIGNED', false, 1],
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
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'topic_id_idx' => ['topic_id'],
                'multi_idx'    => ['poster_id', 'topic_id'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('::posts', $schema);

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
                'word_id_idx' => ['word_id'],
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
                'num_views'      => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'num_replies'    => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'closed'         => ['TINYINT(1)', false, 0],
                'sticky'         => ['TINYINT(1)', false, 0],
                'stick_fp'       => ['TINYINT(1)', false, 0],
                'moved_to'       => ['INT(10) UNSIGNED', false, 0],
                'forum_id'       => ['INT(10) UNSIGNED', false, 0],
                'poll_type'      => ['SMALLINT UNSIGNED', false, 0],
                'poll_time'      => ['INT(10) UNSIGNED', false, 0],
                'poll_term'      => ['TINYINT', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'forum_id_idx'      => ['forum_id'],
                'moved_to_idx'      => ['moved_to'],
                'last_post_idx'     => ['last_post'],
                'first_post_id_idx' => ['first_post_id'],
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
                'jabber'           => ['VARCHAR(80)', false, ''],
                'icq'              => ['VARCHAR(12)', false, ''],
                'msn'              => ['VARCHAR(80)', false, ''],
                'aim'              => ['VARCHAR(30)', false, ''],
                'yahoo'            => ['VARCHAR(30)', false, ''],
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
                'timezone'         => ['FLOAT', false, 0],
                'dst'              => ['TINYINT(1)', false, 0],
                'time_format'      => ['TINYINT(1)', false, 0],
                'date_format'      => ['TINYINT(1)', false, 0],
                'language'         => ['VARCHAR(25)', false, ''],
                'style'            => ['VARCHAR(25)', false, ''],
                'num_posts'        => ['INT(10) UNSIGNED', false, 0],
                'num_topics'       => ['INT(10) UNSIGNED', false, 0],
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
                'gender'           => ['TINYINT UNSIGNED', false, 0],
                'u_mark_all_read'  => ['INT(10) UNSIGNED', false, 0],
                'last_report_id'   => ['INT(10) UNSIGNED', false, 0],
                'ip_check_type'    => ['TINYINT UNSIGNED', false, 0],
                'login_ip_cache'   => ['VARCHAR(255)', false, ''],
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

        $now    = \time();
        $groups = [
            // g_id,                g_title,              g_user_title,        g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_mod_promote_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_post_links, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood, g_promote_min_posts, g_promote_next_group
            [FORK_GROUP_ADMIN,      __('Administrators'), __('Administrator '), 0,          0,                0,                  0,                      0,               1,                   1,            1,            1,              1,             1,            1,              1,               1,            1,           1,        1,              1,            0,            0,              0,             0,              0,                   0,                     ],
            [FORK_GROUP_MOD,        __('Moderators'),     __('Moderator '),     1,          1,                1,                  1,                      1,               1,                   1,            1,            1,              1,             1,            1,              1,               1,            1,           1,        1,              1,            0,            0,              0,             0,              0,                   0,                     ],
            [FORK_GROUP_GUEST,      __('Guests'),         '',                   0,          0,                0,                  0,                      0,               0,                   1,            1,            0,              0,             0,            0,              0,               0,            0,           1,        1,              0,            120,          60,             0,             0,              0,                   0,                     ],
            [FORK_GROUP_MEMBER,     __('Members'),        '',                   0,          0,                0,                  0,                      0,               0,                   1,            1,            1,              1,             1,            1,              1,               1,            0,           1,        1,              1,            30,           30,             60,            60,             0,                   0,                     ],
            [FORK_GROUP_NEW_MEMBER, __('New members'),    __('New member'),     0,          0,                0,                  0,                      0,               0,                   1,            1,            1,              1,             1,            1,              1,               0,            0,           1,        1,              1,            60,           30,             120,           60,             5,                   FORK_GROUP_MEMBER,     ],
        ];

        foreach ($groups as $group) {
            $this->c->DB->exec('INSERT INTO ::groups (g_id, g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_mod_promote_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_post_links, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood, g_promote_min_posts, g_promote_next_group) VALUES (?i, ?s, ?s, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i)', $group) ;
        }

        $this->c->DB->exec('UPDATE ::groups SET g_pm_limit=0 WHERE g_id=?i', [FORK_GROUP_ADMIN]);
        $this->c->DB->exec('UPDATE ::groups SET g_pm=0, g_sig_length=0, g_sig_lines=0 WHERE g_id=?i', [FORK_GROUP_GUEST]);

        if ('pgsql' === $v->dbtype) {
            $this->c->DB->exec('ALTER SEQUENCE ::groups_g_id_seq RESTART WITH ' . (FORK_GROUP_NEW_MEMBER + 1));
        }

        $forkConfig = [
            'i_fork_revision'         => $this->c->FORK_REVISION,
            'o_board_title'           => $v->title,
            'o_board_desc'            => $v->descr,
            'o_default_timezone'      => 0,
            'i_timeout_visit'         => 3600,
            'i_timeout_online'        => 900,
            'i_redirect_delay'        => 1,
            'b_show_user_info'        => 1,
            'b_show_post_count'       => 1,
            'b_smilies'               => 1,
            'b_smilies_sig'           => 1,
            'b_make_links'            => 1,
            'o_default_lang'          => $v->defaultlang,
            'o_default_style'         => $v->defaultstyle,
            'i_default_user_group'    => FORK_GROUP_NEW_MEMBER,
            'i_topic_review'          => 15,
            'i_disp_topics_default'   => 30,
            'i_disp_posts_default'    => 25,
            'i_disp_users'            => 50,
            'b_quickpost'             => 1,
            'b_users_online'          => 1,
            'b_censoring'             => 0,
            'b_show_dot'              => 0,
            'b_topic_views'           => 1,
            'o_additional_navlinks'   => '',
            'i_report_method'         => 0,
            'b_regs_report'           => 0,
            'i_default_email_setting' => 2,
            'o_mailing_list'          => $v->email,
            'b_avatars'               => \filter_var(@\ini_get('file_uploads'), \FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'o_avatars_dir'           => '/img/avatars',
            'i_avatars_width'         => 60,
            'i_avatars_height'        => 60,
            'i_avatars_size'          => 10240,
            'o_admin_email'           => $v->email,
            'o_webmaster_email'       => $v->email,
            'b_forum_subscriptions'   => 1,
            'b_topic_subscriptions'   => 1,
            'i_email_max_recipients'  => 1,
            'o_smtp_host'             => '',
            'o_smtp_user'             => '',
            'o_smtp_pass'             => '',
            'b_smtp_ssl'              => 0,
            'b_regs_allow'            => 1,
            'b_regs_verify'           => 1,
            'b_announcement'          => 0,
            'o_announcement_message'  => __('Announcement '),
            'b_rules'                 => 0,
            'o_rules_message'         => __('Rules '),
            'b_maintenance'           => 0,
            'o_maintenance_message'   => __('Maintenance message '),
            'b_default_dst'           => 0,
            'i_feed_type'             => 2,
            'i_feed_ttl'              => 0,
            'b_message_bbcode'        => 1,
            'b_message_all_caps'      => 0,
            'b_subject_all_caps'      => 0,
            'b_sig_all_caps'          => 0,
            'b_sig_bbcode'            => 1,
            'b_force_guest_email'     => 1,
            'b_pm'                    => 0,
            'b_poll_enabled'          => 0,
            'i_poll_max_questions'    => 3,
            'i_poll_max_fields'       => 20,
            'i_poll_time'             => 60,
            'i_poll_term'             => 3,
            'b_poll_guest'            => 0,
            'a_max_users'             => \json_encode(['number' => 1, 'time' => \time()], self::JSON_OPTIONS),
            'a_bb_white_mes'          => \json_encode([], self::JSON_OPTIONS),
            'a_bb_white_sig'          => \json_encode(['b', 'i', 'u', 'color', 'colour', 'email', 'url'], self::JSON_OPTIONS),
            'a_bb_black_mes'          => \json_encode([], self::JSON_OPTIONS),
            'a_bb_black_sig'          => \json_encode([], self::JSON_OPTIONS),
            'a_guest_set'             => \json_encode(
                [
                    'show_smilies' => 1,
                    'show_sig'     => 1,
                    'show_avatars' => 1,
                    'show_img'     => 1,
                    'show_img_sig' => 1,
                ], self::JSON_OPTIONS
            ),
            's_РЕГИСТР'               => 'Ok',
        ];

        foreach ($forkConfig as $name => $value) {
            $this->c->DB->exec('INSERT INTO ::config (conf_name, conf_value) VALUES (?s, ?s)', [$name, $value]);
        }


        $ip = \filter_var($_SERVER['REMOTE_ADDR'], \FILTER_VALIDATE_IP) ?: '0.0.0.0';
        $topicId = 1;

        $this->c->DB->exec('INSERT INTO ::users (group_id, username, username_normal, password, email, email_normal, language, style, num_posts, last_post, registered, registration_ip, last_visit, signature, num_topics) VALUES (?i, ?s, ?s, ?s, ?s, ?s, ?s, ?s, 1, ?i, ?i, ?s, ?i, \'\', 1)', [FORK_GROUP_ADMIN, $v->username, $this->c->users->normUsername($v->username), \password_hash($v->password, \PASSWORD_DEFAULT), $v->email, $this->c->NormEmail->normalize($v->email), $v->defaultlang, $v->defaultstyle, $now, $now, $ip, $now]);

        $adminId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::categories (cat_name, disp_position) VALUES (?s, ?i)', [__('Test category'), 1]);

        $catId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES(?s, ?i, ?s, ?s, ?i, ?i)', [$v->username, $adminId, $ip, __('Test message'), $now, $topicId]);

        $postId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::forums (forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, last_poster_id, last_topic, disp_position, cat_id, moderators) VALUES (?s, ?s, ?i, ?i, ?i, ?i, ?s, ?i, ?s, ?i, ?i, \'\')', [__('Test forum'), __('This is just a test forum'), 1, 1, $now, $postId, $v->username, $adminId, __('Test post'), 1, $catId]);

        $forumId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::topics (poster, poster_id, subject, posted, first_post_id, last_post, last_post_id, last_poster, last_poster_id, forum_id) VALUES(?s, ?i, ?s, ?i, ?i, ?i, ?i, ?s, ?i, ?i)', [$v->username, $adminId, __('Test post'), $now, $postId, $now, $postId, $v->username, $adminId, $forumId]);

        $topicId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('UPDATE ::posts SET topic_id=?i WHERE id=?i', [$topicId, $postId]);

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

        $query   = 'INSERT INTO ::bbcode (bb_tag, bb_edit, bb_delete, bb_structure)
            VALUES(?s:tag, 1, 0, ?s:structure)';
        $bbcodes = include $this->c->DIR_APP . '/config/defaultBBCode.php';

        foreach ($bbcodes as $bbcode) {
            $vars = [
                ':tag'       => $bbcode['tag'],
                ':structure' => \json_encode($bbcode, self::JSON_OPTIONS),
            ];

            $this->c->DB->exec($query, $vars);
        }

        $config = \file_get_contents($this->c->DIR_APP . '/config/main.dist.php');

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
        $result = \file_put_contents($this->c->DIR_APP . '/config/main.php', $config);

        if (false === $result) {
            throw new RuntimeException('No write to main.php');
        }

        return $this->c->Redirect->toIndex();
    }
}
