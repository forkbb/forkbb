<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use PDO;
use PDOException;
use RuntimeException;

class Install extends Page
{
    const PHP_MIN = '5.6.0';

    /**
     * Для MySQL
     * @var string
     */
    protected $DBEngine = '';

    /**
     * Подготовка страницы к отображению
     */
    public function prepare()
    {
    }

    /**
     * Возращает доступные типы БД
     *
     * @return array
     */
    protected function DBTypes()
    {
        $dbTypes = [];
        $pdoDrivers = PDO::getAvailableDrivers();
        foreach ($pdoDrivers as $type) {
            if (\file_exists($this->c->DIR_APP . '/Core/DB/' . \ucfirst($type) . '.php')) {
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
                }
            }
        }
        return $dbTypes;
    }

    /**
     * Подготовка данных для страницы установки форума
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function install(array $args, $method)
    {
        $changeLang = false;
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'       => 'token:Install',
                    'installlang' => 'required|string:trim',
                    'changelang'  => 'string',
                ]);

            if ($v->validation($_POST)) {
                $this->user->language = $v->installlang;
                $changeLang           = (bool) $v->changelang;
            }
        }
        $v = null;

        $this->c->Lang->load('install');

        // версия PHP
        if (\version_compare(PHP_VERSION, self::PHP_MIN, '<')) {
            $this->fIswev = ['e', \ForkBB\__('You are running error', 'PHP', \PHP_VERSION, $this->c->FORK_REVISION, self::PHP_MIN)];
        }

        // типы БД
        $this->dbTypes = $this->DBTypes();
        if (empty($this->dbTypes)) {
            $this->fIswev = ['e', \ForkBB\__('No DB extensions')];
        }

        // доступность папок на запись
        $folders = [
            $this->c->DIR_CONFIG,
            $this->c->DIR_CACHE,
            $this->c->DIR_PUBLIC . '/img/avatars',
        ];
        foreach ($folders as $folder) {
            if (! \is_writable($folder)) {
                $folder = \str_replace(\dirname($this->c->DIR_APP), '', $folder);
                $this->fIswev = ['e', \ForkBB\__('Alert folder', $folder)];
            }
        }

        // доступность шаблона конфигурации
        $config = @\file_get_contents($this->c->DIR_CONFIG . '/main.dist.php');
        if (false === $config) {
            $this->fIswev = ['e', \ForkBB\__('No access to main.dist.php')];
        }
        unset($config);

        // языки
        $langs = $this->c->Func->getLangs();
        if (empty($langs)) {
            $this->fIswev = ['e', \ForkBB\__('No language packs')];
        }

        // стили
        $styles = $this->c->Func->getStyles();
        if (empty($styles)) {
            $this->fIswev = ['e', \ForkBB\__('No styles')];
        }

        if ('POST' === $method && ! $changeLang && empty($this->a['fIswev']['e'])) { //????
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_prefix' => [$this, 'vCheckPrefix'],
                    'check_host'   => [$this, 'vCheckHost'],
                    'rtrim_url'    => [$this, 'vRtrimURL']
                ])->addRules([
                    'dbtype'       => 'required|string:trim|in:' . \implode(',', \array_keys($this->dbTypes)),
                    'dbhost'       => 'required|string:trim|check_host',
                    'dbname'       => 'required|string:trim',
                    'dbuser'       => 'string:trim',
                    'dbpass'       => 'string:trim',
                    'dbprefix'     => 'string:trim|max:40|check_prefix',
                    'username'     => 'required|string:trim|min:2|max:25',
                    'password'     => 'required|string|min:16|password',
                    'email'        => 'required|string:trim,lower|max:80|email',
                    'title'        => 'required|string:trim|max:255',
                    'descr'        => 'string:trim|max:65000 bytes',
                    'baseurl'      => 'required|string:trim|rtrim_url',
                    'defaultlang'  => 'required|string:trim|in:' . \implode(',', $this->c->Func->getLangs()),
                    'defaultstyle' => 'required|string:trim|in:' . \implode(',', $this->c->Func->getStyles()),
                ])->addAliases([
                    'dbtype'       => 'Database type',
                    'dbhost'       => 'Database server hostname',
                    'dbname'       => 'Database name',
                    'dbuser'       => 'Database username',
                    'dbpass'       => 'Database password',
                    'dbprefix'     => 'Table prefix',
                    'username'     => 'Administrator username',
                    'password'     => 'Administrator passphrase',
                    'title'        => 'Board title',
                    'descr'        => 'Board description',
                    'baseurl'      => 'Base URL',
                    'defaultlang'  => 'Default language',
                    'defaultstyle' => 'Default style',
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
                    [
                        'fields' => [
                            'installlang' => [
                                'type'    => 'select',
                                'options' => \array_combine($langs, $langs),
                                'value'   => $this->user->language,
                                'caption' => \ForkBB\__('Install language'),
                                'info'    => \ForkBB\__('Choose install language info'),
                            ],
                        ],
                    ],
                ],
                'btns'   => [
                    'changelang'  => [
                        'type'  => 'submit',
                        'value' => \ForkBB\__('Change language'),
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
                [
                    'info' => [
                        'info1' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Database setup'),
                            'html'  => true,
                        ],
                        'info2' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Info 1'),
                        ],
                    ],
                ],
                [
                    'fields' => [
                        'dbtype' => [
                            'type'     => 'select',
                            'options'  => $this->dbTypes,
                            'value'    => $v ? $v->dbtype : 'mysql_innodb',
                            'caption'  => \ForkBB\__('Database type'),
                            'info'     => \ForkBB\__('Info 2'),
                            'required' => true,
                        ],
                        'dbhost' => [
                            'type'     => 'text',
                            'value'    => $v ? $v->dbhost : 'localhost',
                            'caption'  => \ForkBB\__('Database server hostname'),
                            'info'     => \ForkBB\__('Info 3'),
                            'required' => true,
                        ],
                        'dbname' => [
                            'type'     => 'text',
                            'value'    => $v ? $v->dbname : '',
                            'caption'  => \ForkBB\__('Database name'),
                            'info'     => \ForkBB\__('Info 4'),
                            'required' => true,
                        ],
                        'dbuser' => [
                            'type'    => 'text',
                            'value'   => $v ? $v->dbuser : '',
                            'caption' => \ForkBB\__('Database username'),
                        ],
                        'dbpass' => [
                            'type'    => 'password',
                            'value'   => '',
                            'caption' => \ForkBB\__('Database password'),
                            'info'    => \ForkBB\__('Info 5'),
                        ],
                        'dbprefix' => [
                            'type'      => 'text',
                            'maxlength' => 40,
                            'value'     => $v ? $v->dbprefix : '',
                            'caption'   => \ForkBB\__('Table prefix'),
                            'info'      => \ForkBB\__('Info 6'),
                        ],
                    ],
                ],
                [
                    'info' => [
                        'info1' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Administration setup'),
                            'html'  => true,
                        ],
                        'info2' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Info 7'),
                        ],
                    ],
                ],
                [
                    'fields' => [
                        'username' => [
                            'type'      => 'text',
                            'maxlength' => 25,
                            'pattern'   => '^.{2,25}$',
                            'value'     => $v ? $v->username : '',
                            'caption'   => \ForkBB\__('Administrator username'),
                            'info'      => \ForkBB\__('Info 8'),
                            'required'  => true,
                        ],
                        'password' => [
                            'type'     => 'password',
                            'pattern'  => '^.{16,}$',
                            'value'    => '',
                            'caption'  => \ForkBB\__('Administrator passphrase'),
                            'info'     => \ForkBB\__('Info 9'),
                            'required' => true,
                        ],
                        'email' => [
                            'type'      => 'text',
                            'maxlength' => 80,
                            'pattern'   => '.+@.+',
                            'value'     => $v ? $v->email : '',
                            'caption'   => \ForkBB\__('Administrator email'),
                            'info'      => \ForkBB\__('Info 10'),
                            'required'  => true,
                        ],

                    ],
                ],
                [
                    'info' => [
                        'info1' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Board setup'),
                            'html'  => true,
                        ],
                        'info2' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Info 11'),
                        ],
                    ],
                ],
                [
                    'fields' => [
                        'title' => [
                            'type'      => 'text',
                            'maxlength' => 255,
                            'value'     => $v ? $v->title : \ForkBB\__('My ForkBB Forum'),
                            'caption'   => \ForkBB\__('Board title'),
                            'required'  => true,
                        ],
                        'descr' => [
                            'type'      => 'text',
                            'maxlength' => 16000,
                            'value'     => $v ? $v->descr : \ForkBB\__('Description'),
                            'caption'   => \ForkBB\__('Board description'),
                        ],
                        'baseurl' => [
                            'type'      => 'text',
                            'maxlength' => 1024,
                            'value'     => $v ? $v->baseurl : $this->c->BASE_URL,
                            'caption'   => \ForkBB\__('Base URL'),
                            'required'  => true,
                        ],
                        'defaultlang' => [
                            'type'      => 'select',
                            'options'   => \array_combine($langs, $langs),
                            'value'     => $v ? $v->defaultlang : $this->user->language,
                            'caption'   => \ForkBB\__('Default language'),
                            'required'  => true,
                        ],
                        'defaultstyle' => [
                            'type'      => 'select',
                            'options'   => \array_combine($styles, $styles),
                            'value'     => $v ? $v->defaultstyle : $this->user->style,
                            'caption'   => \ForkBB\__('Default style'),
                            'required'  => true,
                        ],

                    ],
                ],
            ],
            'btns'   => [
                'submit'  => [
                    'type'  => 'submit',
                    'value' => \ForkBB\__('Start install'),
                ],
            ],
        ];

        $this->nameTpl    = 'layouts/install';
        $this->onlinePos  = null;
        $this->robots     = 'noindex';
        $this->rev        = $this->c->FORK_REVISION;

        return $this;
    }

    /**
     * Обработка base URL
     *
     * @param Validator $v
     * @param string $url
     *
     * @return string
     */
    public function vRtrimURL(Validator $v, $url)
    {
        return \rtrim($url, '/');
    }

    /**
     * Дополнительная проверка префикса
     *
     * @param Validator $v
     * @param string $prefix
     *
     * @return string
     */
    public function vCheckPrefix(Validator $v, $prefix)
    {
        if (isset($prefix{0})) {
            if (! \preg_match('%^[a-z][a-z\d_]*$%i', $prefix)) {
                $v->addError('Table prefix error');
            } elseif ('sqlite' === $v->dbtype && 'sqlite_' === \strtolower($prefix)) {
                $v->addError('Prefix reserved');
            }
        }
        return $prefix;
    }

    /**
     * Полная проверка подключения к БД
     *
     * @param Validator $v
     * @param string $dbhost
     *
     * @return string
     */
    public function vCheckHost(Validator $v, $dbhost)
    {
        $this->c->DB_USERNAME = $v->dbuser;
        $this->c->DB_PASSWORD = $v->dbpass;
        $this->c->DB_PREFIX   = $v->dbprefix;
        $dbtype               = $v->dbtype;
        $dbname               = $v->dbname;

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
                break;
            case 'pgsql':
                break;
            default:
                //????
        }
        $this->c->DB_OPTIONS  = [];

        // подключение к БД
        try {
            $stat = $this->c->DB->statistics();
        } catch (PDOException $e) {
            $v->addError($e->getMessage());
            return $dbhost;
        }

        // проверка наличия таблицы пользователей в БД
        try {
            $stmt = $this->c->DB->query('SELECT 1 FROM ::users LIMIT 1');
            if (! empty($stmt->fetch())) {
                $v->addError(\ForkBB\__('Existing table error', $v->dbprefix, $v->dbname));
                return $dbhost;
            }
        } catch (PDOException $e) {
            // все отлично, таблица пользователей не найдена
        }

        // база MySQL, кодировка базы отличается от UTF-8 (4 байта)
        if (isset($stat['character_set_database']) && 'utf8mb4' !== $stat['character_set_database']) {
            $v->addError('Bad database charset');
        }

        return $dbhost;
    }

    /**
     * Завершение установки форума
     *
     * @param Validator $v
     *
     * @return Page
     */
    protected function installEnd(Validator $v)
    {
        @\set_time_limit(0);
        $this->c->Cache->clear();

        $this->c->DB->beginTransaction();

        // bans
        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'username'    => ['VARCHAR(190)', true],
                'ip'          => ['VARCHAR(255)', true],
                'email'       => ['VARCHAR(80)', true],
                'message'     => ['VARCHAR(255)', true],
                'expire'      => ['INT(10) UNSIGNED', true],
                'ban_creator' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'username_idx' => ['username(25)'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('bans', $schema);

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
        $this->c->DB->createTable('categories', $schema);

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
        $this->c->DB->createTable('censoring', $schema);

        // config
        $schema = [
            'FIELDS' => [
                'conf_name'  => ['VARCHAR(190)', false, ''],
                'conf_value' => ['TEXT', true],
            ],
            'PRIMARY KEY' => ['conf_name'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('config', $schema);

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
        $this->c->DB->createTable('forum_perms', $schema);

        // forums
        $schema = [
            'FIELDS' => [
                'id'              => ['SERIAL', false],
                'forum_name'      => ['VARCHAR(80)', false, 'New forum'],
                'forum_desc'      => ['TEXT', true],
                'redirect_url'    => ['VARCHAR(255)', false, ''],
                'moderators'      => ['TEXT', true],
                'num_topics'      => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'num_posts'       => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'last_post'       => ['INT(10) UNSIGNED', true],
                'last_post_id'    => ['INT(10) UNSIGNED', true],
                'last_poster'     => ['VARCHAR(190)', true],
                'last_topic'      => ['VARCHAR(255)', true],
                'sort_by'         => ['TINYINT(1)', false, 0],
                'disp_position'   => ['INT(10)', false, 0],
                'cat_id'          => ['INT(10) UNSIGNED', false, 0],
                'no_sum_mess'     => ['TINYINT(1)', false, 0],
                'parent_forum_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('forums', $schema);

        // groups
        $schema = [
            'FIELDS' => [
                'g_id'                   => ['SERIAL', false],
                'g_title'                => ['VARCHAR(50)', false, ''],
                'g_user_title'           => ['VARCHAR(50)', true],
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
            ],
            'PRIMARY KEY' => ['g_id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('groups', $schema);

        // online
        $schema = [
            'FIELDS' => [
                'user_id'     => ['INT(10) UNSIGNED', false, 1],
                'ident'       => ['VARCHAR(190)', false, ''],
                'logged'      => ['INT(10) UNSIGNED', false, 0],
                'last_post'   => ['INT(10) UNSIGNED', true],
                'last_search' => ['INT(10) UNSIGNED', true],
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
        $this->c->DB->createTable('online', $schema);

        // posts
        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'poster'       => ['VARCHAR(190)', false, ''],
                'poster_id'    => ['INT(10) UNSIGNED', false, 1],
                'poster_ip'    => ['VARCHAR(45)', true],
                'poster_email' => ['VARCHAR(80)', true],
                'message'      => ['MEDIUMTEXT', false, ''],
                'hide_smilies' => ['TINYINT(1)', false, 0],
                'edit_post'    => ['TINYINT(1)', false, 0],
                'posted'       => ['INT(10) UNSIGNED', false, 0],
                'edited'       => ['INT(10) UNSIGNED', true],
                'edited_by'    => ['VARCHAR(190)', true],
                'user_agent'   => ['VARCHAR(255)', true],
                'topic_id'     => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'topic_id_idx' => ['topic_id'],
                'multi_idx'    => ['poster_id', 'topic_id'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('posts', $schema);

        // reports
        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'post_id'     => ['INT(10) UNSIGNED', false, 0],
                'topic_id'    => ['INT(10) UNSIGNED', false, 0],
                'forum_id'    => ['INT(10) UNSIGNED', false, 0],
                'reported_by' => ['INT(10) UNSIGNED', false, 0],
                'created'     => ['INT(10) UNSIGNED', false, 0],
                'message'     => ['TEXT', true],
                'zapped'      => ['INT(10) UNSIGNED', true],
                'zapped_by'   => ['INT(10) UNSIGNED', true],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'zapped_idx' => ['zapped'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('reports', $schema);

        // search_cache
        $schema = [
            'FIELDS' => [
                'id'          => ['INT(10) UNSIGNED', false, 0],
                'ident'       => ['VARCHAR(200)', false, ''],
                'search_data' => ['MEDIUMTEXT', true],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'ident_idx' => ['ident(8)'], //????
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('search_cache', $schema);

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
        $this->c->DB->createTable('search_matches', $schema);

        // search_words
        $schema = [
            'FIELDS' => [
                'id'   => ['SERIAL', false],
                'word' => ['VARCHAR(20)', false, '' , 'bin'],
            ],
            'PRIMARY KEY' => ['word'],
            'INDEXES' => [
                'id_idx' => ['id'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        if ('sqlite' === $v->dbtype) { //????
            $schema['PRIMARY KEY'] = ['id'];
            $schema['UNIQUE KEYS'] = ['word_idx' => ['word']];
        }
        $this->c->DB->createTable('search_words', $schema);

        // topic_subscriptions
        $schema = [
            'FIELDS' => [
                'user_id'  => ['INT(10) UNSIGNED', false, 0],
                'topic_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['user_id', 'topic_id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('topic_subscriptions', $schema);

        // forum_subscriptions
        $schema = [
            'FIELDS' => [
                'user_id'  => ['INT(10) UNSIGNED', false, 0],
                'forum_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['user_id', 'forum_id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('forum_subscriptions', $schema);

        // topics
        $schema = [
            'FIELDS' => [
                'id'            => ['SERIAL', false],
                'poster'        => ['VARCHAR(190)', false, ''],
                'subject'       => ['VARCHAR(255)', false, ''],
                'posted'        => ['INT(10) UNSIGNED', false, 0],
                'first_post_id' => ['INT(10) UNSIGNED', false, 0],
                'last_post'     => ['INT(10) UNSIGNED', false, 0],
                'last_post_id'  => ['INT(10) UNSIGNED', false, 0],
                'last_poster'   => ['VARCHAR(190)', true],
                'num_views'     => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'num_replies'   => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'closed'        => ['TINYINT(1)', false, 0],
                'sticky'        => ['TINYINT(1)', false, 0],
                'stick_fp'      => ['TINYINT(1)', false, 0],
                'moved_to'      => ['INT(10) UNSIGNED', true],
                'forum_id'      => ['INT(10) UNSIGNED', false, 0],
                'poll_type'     => ['TINYINT(4)', false, 0],
                'poll_time'     => ['INT(10) UNSIGNED', false, 0],
                'poll_term'     => ['TINYINT(4)', false, 0],
                'poll_kol'      => ['INT(10) UNSIGNED', false, 0],
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
        $this->c->DB->createTable('topics', $schema);

        // pms_new_block
        $schema = [
            'FIELDS' => [
                'bl_id'      => ['INT(10) UNSIGNED', false, 0],
                'bl_user_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'INDEXES' => [
                'bl_id_idx'      => ['bl_id'],
                'bl_user_id_idx' => ['bl_user_id']
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('pms_new_block', $schema);

        // pms_new_posts
        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'poster'       => ['VARCHAR(190)', false, ''],
                'poster_id'    => ['INT(10) UNSIGNED', false, 1],
                'poster_ip'    => ['VARCHAR(45)', true],
                'message'      => ['TEXT', true],
                'hide_smilies' => ['TINYINT(1)', false, 0],
                'posted'       => ['INT(10) UNSIGNED', false, 0],
                'edited'       => ['INT(10) UNSIGNED', true],
                'edited_by'    => ['VARCHAR(190)', true],
                'post_new'     => ['TINYINT(1)', false, 1],
                'topic_id'     => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'topic_id_idx' => ['topic_id'],
                'multi_idx'    => ['poster_id', 'topic_id'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('pms_new_posts', $schema);

        // pms_new_topics
        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'topic'       => ['VARCHAR(255)', false, ''],
                'starter'     => ['VARCHAR(190)', false, ''],
                'starter_id'  => ['INT(10) UNSIGNED', false, 0],
                'to_user'     => ['VARCHAR(190)', false, ''],
                'to_id'       => ['INT(10) UNSIGNED', false, 0],
                'replies'     => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'last_posted' => ['INT(10) UNSIGNED', false, 0],
                'last_poster' => ['TINYINT(1)', false, 0],
                'see_st'      => ['INT(10) UNSIGNED', false, 0],
                'see_to'      => ['INT(10) UNSIGNED', false, 0],
                'topic_st'    => ['TINYINT(4)', false, 0],
                'topic_to'    => ['TINYINT(4)', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'multi_idx_st' => ['starter_id', 'topic_st'],
                'multi_idx_to' => ['to_id', 'topic_to'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('pms_new_topics', $schema);

        // users
        $schema = [
            'FIELDS' => [
                'id'               => ['SERIAL', false],
                'group_id'         => ['INT(10) UNSIGNED', false, 0],
                'username'         => ['VARCHAR(190)', false, ''],
                'password'         => ['VARCHAR(255)', false, ''],
                'email'            => ['VARCHAR(80)', false, ''],
                'email_confirmed'  => ['TINYINT(1)', false, 0],
                'title'            => ['VARCHAR(50)', true],
                'realname'         => ['VARCHAR(40)', true],
                'url'              => ['VARCHAR(100)', true],
                'jabber'           => ['VARCHAR(80)', true],
                'icq'              => ['VARCHAR(12)', true],
                'msn'              => ['VARCHAR(80)', true],
                'aim'              => ['VARCHAR(30)', true],
                'yahoo'            => ['VARCHAR(30)', true],
                'location'         => ['VARCHAR(30)', true],
                'signature'        => ['TEXT', true],
                'disp_topics'      => ['TINYINT(3) UNSIGNED', true],
                'disp_posts'       => ['TINYINT(3) UNSIGNED', true],
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
                'language'         => ['VARCHAR(25)', false, $v->defaultlang],
                'style'            => ['VARCHAR(25)', false, $v->defaultstyle],
                'num_posts'        => ['INT(10) UNSIGNED', false, 0],
                'last_post'        => ['INT(10) UNSIGNED', true],
                'last_search'      => ['INT(10) UNSIGNED', true],
                'last_email_sent'  => ['INT(10) UNSIGNED', true],
                'last_report_sent' => ['INT(10) UNSIGNED', true],
                'registered'       => ['INT(10) UNSIGNED', false, 0],
                'registration_ip'  => ['VARCHAR(45)', false, ''],
                'last_visit'       => ['INT(10) UNSIGNED', false, 0],
                'admin_note'       => ['VARCHAR(30)', true],
                'activate_string'  => ['VARCHAR(80)', true],
                'activate_key'     => ['VARCHAR(8)', true],    //????
                'messages_enable'  => ['TINYINT(1)', false, 1],
                'messages_email'   => ['TINYINT(1)', false, 0],
                'messages_flag'    => ['TINYINT(1)', false, 0],
                'messages_new'     => ['INT(10) UNSIGNED', false, 0],
                'messages_all'     => ['INT(10) UNSIGNED', false, 0],
                'pmsn_last_post'   => ['INT(10) UNSIGNED', true],
                'warning_flag'     => ['TINYINT(1)', false, 0],
                'warning_all'      => ['INT(10) UNSIGNED', false, 0],
                'gender'           => ['TINYINT(4) UNSIGNED', false, 0],
                'u_mark_all_read'  => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'UNIQUE KEYS' => [
                'username_idx' => ['username(25)'],
            ],
            'INDEXES' => [
                'registered_idx' => ['registered'],
            ],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('users', $schema);

        // smilies
        $schema = [
            'FIELDS' => [
                'id'            => ['SERIAL', false],
                'image'         => ['VARCHAR(40)', false, ''],
                'text'          => ['VARCHAR(20)', false, ''],
                'disp_position' => ['TINYINT(4) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('smilies', $schema);

        // warnings
        $schema = [
            'FIELDS' => [
                'id'        => ['SERIAL', false],
                'poster'    => ['VARCHAR(190)', false, ''],
                'poster_id' => ['INT(10) UNSIGNED', false, 0],
                'posted'    => ['INT(10) UNSIGNED', false, 0],
                'message'   => ['TEXT', true],
            ],
            'PRIMARY KEY' => ['id'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('warnings', $schema);

        // poll
        $schema = [
            'FIELDS' => [
                'tid'      => ['INT(10) UNSIGNED', false, 0],
                'question' => ['TINYINT(4)', false, 0],
                'field'    => ['TINYINT(4)', false, 0],
                'choice'   => ['VARCHAR(255)', false, ''],
                'votes'    => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['tid', 'question', 'field'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('poll', $schema);

        // poll_voted
        $schema = [
            'FIELDS' => [
                'tid' => ['INT(10) UNSIGNED', false],
                'uid' => ['INT(10) UNSIGNED', false],
                'rez' => ['TEXT', true],
            ],
            'PRIMARY KEY' => ['tid', 'uid'],
            'ENGINE' => $this->DBEngine,
        ];
        $this->c->DB->createTable('poll_voted', $schema) ;

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
        $this->c->DB->createTable('mark_of_forum', $schema);

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
        $this->c->DB->createTable('mark_of_topic', $schema);

        $now = \time();

        $groups = [
            // g_id,                 g_title,                      g_user_title,        g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_mod_promote_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood
            [$this->c->GROUP_ADMIN,  \ForkBB\__('Administrators'), \ForkBB\__('Administrator '), 0,           0,                0,                  0,                      0,               1,                   1,            1,            1,              1,             1,            1,              1,               1,           1,        1,              1,            0,            0,              0,             0],
            [$this->c->GROUP_MOD,    \ForkBB\__('Moderators'),     \ForkBB\__('Moderator '),     1,           1,                1,                  1,                      1,               1,                   1,            1,            1,              1,             1,            1,              1,               1,           1,        1,              1,            0,            0,              0,             0],
            [$this->c->GROUP_GUEST,  \ForkBB\__('Guests'),         NULL,                         0,           0,                0,                  0,                      0,               0,                   1,            1,            0,              0,             0,            0,              0,               0,           1,        1,              0,            120,          60,             0,             0],
            [$this->c->GROUP_MEMBER, \ForkBB\__('Members'),        NULL,                         0,           0,                0,                  0,                      0,               0,                   1,            1,            1,              1,             1,            1,              1,               0,           1,        1,              1,            30,           30,             60,            60],
        ];
        foreach ($groups as $group) { //???? $db_type != 'pgsql'
            $this->c->DB->exec('INSERT INTO ::groups (g_id, g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_mod_promote_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood) VALUES (?i, ?s, ?s, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i)', $group) ;
        }
        $this->c->DB->exec('UPDATE ::groups SET g_pm_limit=0 WHERE g_id=?i', [$this->c->GROUP_ADMIN]);

        $ip = \filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ?: 'unknow';
        $this->c->DB->exec('INSERT INTO ::users (group_id, username, password, email) VALUES (?i, ?s, ?s, ?s)', [$this->c->GROUP_GUEST, \ForkBB\__('Guest '), \ForkBB\__('Guest '), \ForkBB\__('Guest ')]);
        $this->c->DB->exec('INSERT INTO ::users (group_id, username, password, email, language, style, num_posts, last_post, registered, registration_ip, last_visit) VALUES (?i, ?s, ?s, ?s, ?s, ?s, ?i, ?i, ?i, ?s, ?i)', [$this->c->GROUP_ADMIN, $v->username, password_hash($v->password, PASSWORD_DEFAULT), $v->email, $v->defaultlang, $v->defaultstyle, 1, $now, $now, $ip, $now]);

        $pun_config = [
            'i_fork_revision'         => $this->c->FORK_REVISION,
            'o_board_title'           => $v->title,
            'o_board_desc'            => $v->descr,
            'o_default_timezone'      => 0,
            'o_time_format'           => 'H:i:s',
            'o_date_format'           => 'Y-m-d',
            'o_timeout_visit'         => 3600,
            'o_timeout_online'        => 900,
            'o_redirect_delay'        => 1,
            'o_show_version'          => 0,
            'o_show_user_info'        => 1,
            'o_show_post_count'       => 1,
            'o_signatures'            => 1,
            'o_smilies'               => 1,
            'o_smilies_sig'           => 1,
            'o_make_links'            => 1,
            'o_default_lang'          => $v->defaultlang,
            'o_default_style'         => $v->defaultstyle,
            'o_default_user_group'    => $this->c->GROUP_MEMBER,
            'o_topic_review'          => 15,
            'o_disp_topics_default'   => 30,
            'o_disp_posts_default'    => 25,
            'o_disp_users'            => 50,
            'o_indent_num_spaces'     => 4,
            'o_quote_depth'           => 3,
            'o_quickpost'             => 1,
            'o_users_online'          => 1,
            'o_censoring'             => 0,
            'o_show_dot'              => 0,
            'o_topic_views'           => 1,
            'o_quickjump'             => 1,
            'o_gzip'                  => 0,
            'o_additional_navlinks'   => '',
            'o_report_method'         => 0,
            'o_regs_report'           => 0,
            'o_default_email_setting' => 1,
            'o_mailing_list'          => $v->email,
            'o_avatars'               => \in_array(\strtolower(@\ini_get('file_uploads')), ['on', 'true', '1']) ? 1 : 0,
            'o_avatars_dir'           => '/img/avatars',
            'o_avatars_width'         => 60,
            'o_avatars_height'        => 60,
            'o_avatars_size'          => 10240,
            'o_search_all_forums'     => 1,
            'o_admin_email'           => $v->email,
            'o_webmaster_email'       => $v->email,
            'o_forum_subscriptions'   => 1,
            'o_topic_subscriptions'   => 1,
            'o_smtp_host'             => NULL,
            'o_smtp_user'             => NULL,
            'o_smtp_pass'             => NULL,
            'o_smtp_ssl'              => 0,
            'o_regs_allow'            => 1,
            'o_regs_verify'           => 1,
            'o_announcement'          => 0,
            'o_announcement_message'  => \ForkBB\__('Announcement '),
            'o_rules'                 => 0,
            'o_rules_message'         => \ForkBB\__('Rules '),
            'o_maintenance'           => 0,
            'o_maintenance_message'   => \ForkBB\__('Maintenance message '),
            'o_default_dst'           => 0,
            'o_feed_type'             => 2,
            'o_feed_ttl'              => 0,
            'p_message_bbcode'        => 1,
            'p_message_img_tag'       => 1,
            'p_message_all_caps'      => 1,
            'p_subject_all_caps'      => 1,
            'p_sig_all_caps'          => 1,
            'p_sig_bbcode'            => 1,
            'p_sig_img_tag'           => 0,
            'p_sig_length'            => 400,
            'p_sig_lines'             => 4,
            'p_force_guest_email'     => 1,
            'o_pms_enabled'           => 1,                    // New PMS - Visman
            'o_pms_min_kolvo'         => 0,
            'o_merge_timeout'         => 86400,        // merge post - Visman
            'o_board_redirect'        => '',    // для редиректа - Visman
            'o_board_redirectg'       => 0,
            'o_poll_enabled'          => 0,    // опросы - Visman
            'o_poll_max_ques'         => 3,
            'o_poll_max_field'        => 20,
            'o_poll_time'             => 60,
            'o_poll_term'             => 3,
            'o_poll_guest'            => 0,
            'o_fbox_guest'            => 0,    // Fancybox - Visman
            'o_fbox_files'            => 'viewtopic.php,search.php,pmsnew.php',
            'o_coding_forms'          => 1,    // кодирование форм - Visman
            'o_check_ip'              => 0,    // проверка ip администрации - Visman
            'o_crypto_enable'         => 1,    // случайные имена полей форм - Visman
            'o_crypto_pas'            => $this->c->Secury->randomPass(25),
            'o_crypto_salt'           => $this->c->Secury->randomPass(13),
            'o_enable_acaptcha'       => 1, // математическая каптча
            'st_max_users'            => 1,    // статистика по максимуму юзеров - Visman
            'st_max_users_time'       => \time(),
        ];
        foreach ($pun_config as $conf_name => $conf_value) {
            $this->c->DB->exec('INSERT INTO ::config (conf_name, conf_value) VALUES (?s, ?s)', [$conf_name, $conf_value]);
        }

        $this->c->DB->exec('INSERT INTO ::categories (cat_name, disp_position) VALUES (?s, ?i)', [\ForkBB\__('Test category'), 1]);
        $this->c->DB->exec('INSERT INTO ::forums (forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, last_topic, disp_position, cat_id) VALUES (?s, ?s, ?i, ?i, ?i, ?i, ?s, ?s, ?i, ?i)', [\ForkBB\__('Test forum'), \ForkBB\__('This is just a test forum'), 1, 1, $now, 1, $v->username, \ForkBB\__('Test post'), 1, 1]);
        $this->c->DB->exec('INSERT INTO ::topics (poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, forum_id) VALUES(?s, ?s, ?i, ?i, ?i, ?i, ?s, ?i)', [$v->username, \ForkBB\__('Test post'), $now, 1, $now, 1, $v->username, 1]);
        $this->c->DB->exec('INSERT INTO ::posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES(?s, ?i, ?s, ?s, ?i, ?i)', [$v->username, 2, $ip, \ForkBB\__('Test message'), $now, 1]);

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
            $this->c->DB->exec('INSERT INTO ::smilies (image, text, disp_position) VALUES(?s, ?s, ?i)', [$img, $text, $i++]); //????
        }

        $this->c->DB->commit();

        $config = @\file_get_contents($this->c->DIR_CONFIG . '/main.dist.php');
        if (false === $config) {
            throw new RuntimeException('No access to main.dist.php.');
        }

        $repl = [ //????
            '_BASE_URL_'      => $v->baseurl,
            '_DB_DSN_'        => $this->c->DB_DSN,
            '_DB_USERNAME_'   => $this->c->DB_USERNAME,
            '_DB_PASSWORD_'   => $this->c->DB_PASSWORD,
            '_DB_PREFIX_'     => $this->c->DB_PREFIX,
            '_SALT_FOR_HMAC_' => $this->c->Secury->randomPass(mt_rand(20,30)),
            '_COOKIE_PREFIX_' => 'fork' . $this->c->Secury->randomHash(7) . '_',
            '_COOKIE_KEY1_'   => $this->c->Secury->randomPass(mt_rand(20,30)),
            '_COOKIE_KEY2_'   => $this->c->Secury->randomPass(mt_rand(20,30)),
        ];
        foreach ($repl as $key => $val) {
            $config = \str_replace($key, \addslashes($val), $config);
        }
        $result = \file_put_contents($this->c->DIR_CONFIG . '/main.php', $config);
        if (false === $result) {
            throw new RuntimeException('No write to main.php');
        }

        return $this->c->Redirect->toIndex();
    }
}
