<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Container;
use ForkBB\Models\Validator;
use PDO;
use PDOException;

class Install extends Page
{
    /**
     * Имя шаблона
     * @var string
     */
    protected $nameTpl = 'layouts/install';

    /**
     * Позиция для таблицы онлайн текущего пользователя
     * @var null|string
     */
    protected $onlinePos = null;

    /**
     * Для MySQL
     * @var string
     */
    protected $DBEngine = '';

    /**
     * Конструктор
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->c = $container;
        $this->config = $container->config;
        $container->Lang->load('common', $this->config['o_default_lang']);
    }

    /**
     * Возвращает данные для шаблона
     * @return array
     */
    public function getData()
    {
        return $this->data + [
            'pageHeads' => $this->pageHeads(),
            'fLang' => __('lang_identifier'),
            'fDirection' => __('lang_direction'),
            'fIswev' => $this->getIswev(),
        ];
    }

    /**
     * Возращает типы БД поддерживаемые PDO
     * @param string $curType
     * @return array
     */
    protected function getDBTypes($curType = null)
    {
        $dbTypes = [];
        $pdoDrivers = PDO::getAvailableDrivers();
        foreach ($pdoDrivers as $type) {
            if (file_exists($this->c->DIR_APP . '/Core/DB/' . ucfirst($type) . '.php')) {
                $slctd = $type == $curType ? true : null;
                switch ($type) {
                    case 'mysql':
                        $dbTypes[$type] = ['MySQL (PDO)', $slctd];

                        $type = 'mysql_innodb';
                        $slctd = $type == $curType ? true : null;
                        $dbTypes[$type] = ['MySQL (PDO) InnoDB', $slctd];
                        break;
                    case 'sqlite':
                        $dbTypes[$type] = ['SQLite (PDO)', $slctd];
                        break;
                    case 'pgsql':
                        $dbTypes[$type] = ['PostgreSQL (PDO)', $slctd];
                        break;
                    default:
                        $dbTypes[$type] = [ucfirst($type) . ' (PDO)', $slctd];
                }
            }
        }
        return $dbTypes;
    }

    /**
     * Подготовка данных для страницы установки форума
     * @param array $args
     * @return Page
     */
    public function install(array $args)
    {
        // язык
        $langs = $this->c->Func->getLangs();
        if (empty($langs)) {
            $this->iswev['e'][] = 'No language pack.';
            $installLang = $installLangs = $defaultLangs = 'English';
        } else {
            if (isset($args['installlang'])) {
                $this->c->user->language = $args['installlang'];
            }

            $this->c->Lang->load('install');

            if (count($langs) > 1) {
                $installLang = $this->c->user->language;
                $defLang = isset($args['defaultlang']) ? $args['defaultlang'] : $installLang;
                $installLangs = $defaultLangs = [];
                foreach ($langs as $lang) {
                    $installLangs[] = $lang == $installLang ? [$lang, 1] : [$lang];
                    $defaultLangs[] = $lang == $defLang ? [$lang, 1] : [$lang];
                }
            } else {
                $installLang = $installLangs = $defaultLangs = $langs[0];
            }

        }
        unset($args['installlang']);
        // версия PHP
        $phpMin = '5.6.0';
        if (version_compare(PHP_VERSION, $phpMin, '<')) {
            $this->iswev['e'][] = __('You are running error', 'PHP', PHP_VERSION, $this->c->FORK_REVISION, $phpMin);
        }
        // доступность папок на запись
        $folders = [
            $this->c->DIR_CONFIG,
            $this->c->DIR_CACHE,
            $this->c->DIR_PUBLIC . '/avatar',
        ];
        foreach ($folders as $folder) {
            if (! is_writable($folder)) {
                $this->iswev['e'][] = __('Alert folder', $folder);
            }
        }
        // стиль
        $styles = $this->c->Func->getStyles();
        if (empty($styles)) {
            $this->iswev['e'][] = __('No styles');
            $defaultStyles = ['ForkBB'];
        } else {
            $defaultStyles = [];
            $defStyle = isset($args['defaultstyle']) ? $args['defaultstyle'] : $this->c->user->style;
            foreach ($styles as $style) {
                $defaultStyles[] = $style == $defStyle ? [$style, 1] : [$style];
            }
        }
        unset($args['defaultstyle']);
        // типы БД
        $dbTypes = $this->getDBTypes(isset($args['dbtype']) ? $args['dbtype'] : null);
        if (empty($dbTypes)) {
            $this->iswev['e'][] = __('No DB extensions');
        }
        unset($args['dbtype']);

        $this->data = $args + [
            'rev' => $this->c->FORK_REVISION,
            'formAction' => $this->c->Router->link('Install'),
            'installLangs' => $installLangs,
            'installLang' => $installLang,
            'dbTypes' => $dbTypes,
            'dbhost' => 'localhost',
            'dbname' => '',
            'dbuser' => '',
            'dbprefix' => '',
            'username' => '',
            'email' => '',
            'title' => __('My ForkBB Forum'),
            'descr' => __('Description'),
            'baseurl' => $this->c->BASE_URL,
            'defaultLangs' => $defaultLangs,
            'defaultStyles' => $defaultStyles,
        ];
        return $this;
    }

    /**
     * Начальная стадия установки
     * @return Page
     */
    public function installPost()
    {
        $v = $this->c->Validator->setRules([
            'installlang' => 'string:trim',
            'changelang' => 'string',
        ]);
        $v->validation($_POST);

        $installLang = $v->installlang;

        if (isset($v->changelang)) {
            return $this->install(['installlang' => $installLang]);
        }

        $this->c->user->language = $installLang;
        $this->c->Lang->load('install');

        $v = $this->c->Validator->addValidators([
            'check_prefix' => [$this, 'vCheckPrefix'],
            'check_host'   => [$this, 'vCheckHost'],
            'rtrim_url'    => [$this, 'vRtrimURL']
        ])->setRules([
            'installlang' => 'string:trim',
            'dbtype' => ['required|string:trim|in:' . implode(',', array_keys($this->getDBTypes())), __('Database type')],
            'dbhost' => ['required|string:trim|check_host', __('Database server hostname')],
            'dbname' => ['required|string:trim', __('Database name')],
            'dbuser' => ['string:trim', __('Database username')],
            'dbpass' => ['string:trim', __('Database password')],
            'dbprefix' => ['string:trim|max:40|check_prefix', __('Table prefix')],
            'username' => ['required|string:trim|min:2|max:25', __('Administrator username')],
            'password' => ['required|string|min:8|password', __('Administrator password')],
            'email' => 'required|string:trim,lower|email',
            'title' => ['required|string:trim', __('Board title')],
            'descr' => ['required|string:trim', __('Board description')],
            'baseurl' => ['required|string:trim|rtrim_url', __('Base URL')],
            'defaultlang' => ['required|string:trim|in:' . implode(',', $this->c->Func->getLangs()), __('Default language')],
            'defaultstyle' => ['required|string:trim|in:' . implode(',', $this->c->Func->getStyles()), __('Default style')],
        ])->setMessages([
            'email' => __('Wrong email'),
        ]);

        if ($v->validation($_POST)) {
            return $this->installEnd($v);
        } else {
            $this->iswev = $v->getErrors();
            return $this->install($v->getData());
        }
    }

    /**
     * Обработка base URL
     * @param Validator $v
     * @param string $url
     * @param int $type
     * @return array
     */
    public function vRtrimURL(Validator $v, $url, $type)
    {
        return [rtrim($url, '/'), $type, false];
    }

    /**
     * Дополнительная проверка префикса
     * @param Validator $v
     * @param string $prefix
     * @param int $type
     * @return array
     */
    public function vCheckPrefix(Validator $v, $prefix, $type)
    {
        $error = false;
        if (strlen($prefix) == 0) {
        } elseif (! preg_match('%^[a-z][a-z\d_]*$%i', $prefix)) {
            $error = __('Table prefix error', $prefix);
        } elseif ($v->dbtype == 'sqlite' && strtolower($prefix) == 'sqlite_') {
            $error = __('Prefix reserved');
        }
        return [$prefix, $type, $error];
    }

    /**
     * Полная проверка подключения к БД
     * @param Validator $v
     * @param string $dbhost
     * @param int $type
     * @return array
     */
    public function vCheckHost(Validator $v, $dbhost, $type)
    {
        $this->c->DB_USERNAME = $v->dbuser;
        $this->c->DB_PASSWORD = $v->dbpass;
        $this->c->DB_PREFIX   = $v->dbprefix;
        $dbtype = $v->dbtype;
        $dbname = $v->dbname;
        // есть ошибки, ни чего не проверяем
        if (! empty($v->getErrors())) {
            return [$dbhost, $type, false];
        }
        // настройки подключения БД
        $DBEngine = 'MyISAM';
        switch ($dbtype) {
            case 'mysql_innodb':
                $DBEngine = 'InnoDB';
            case 'mysql':
                $this->DBEngine = $DBEngine;
                if (preg_match('%^([^:]+):(\d+)$%', $dbhost, $matches)) {
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
            return [$dbhost, $type, $e->getMessage()];
        }
        // проверка наличия таблицы пользователей в БД
        try {
            $stmt = $this->c->DB->query('SELECT 1 FROM ::users WHERE id=1 LIMIT 1');
            if (! empty($stmt->fetch())) {
                return [$dbhost, $type, __('Existing table error', $v->dbprefix, $v->dbname)];
            }
        } catch (PDOException $e) {
            // все отлично, таблица пользователей не найдена
        }
        // база MySQL, кодировка базы UTF-8 (3 байта)
        if (isset($stat['character_set_database']) && $stat['character_set_database'] == 'utf8') {
            $this->c->DB_DSN = str_replace('charset=utf8mb4', 'charset=utf8', $this->c->DB_DSN);
        }
        return [$dbhost, $type, false];
    }

    /**
     * Завершение установки форума
     * @param Validator $v
     * @return Page
     */
    protected function installEnd(Validator $v)
    {
        $this->c->DB->beginTransaction();

        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'username'    => ['VARCHAR(200)', true],
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

        $schema = [
            'FIELDS' => [
                'id'            => ['SERIAL', false],
                'cat_name'      => ['VARCHAR(80)', false, 'New Category'],
                'disp_position' => ['INT(10)', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
        ];
        $this->c->DB->createTable('categories', $schema);

        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'search_for'   => ['VARCHAR(60)', false, ''],
                'replace_with' => ['VARCHAR(60)', false, ''],
            ],
            'PRIMARY KEY' => ['id'],
        ];
        $this->c->DB->createTable('censoring', $schema);

        $schema = [
            'FIELDS' => [
                'conf_name'  => ['VARCHAR(255)', false, ''],
                'conf_value' => ['TEXT', true],
            ],
            'PRIMARY KEY' => ['conf_name'],
        ];
        $this->c->DB->createTable('config', $schema);

        $schema = [
            'FIELDS' => [
                'group_id'     => ['INT(10)', false, 0],
                'forum_id'     => ['INT(10)', false, 0],
                'read_forum'   => ['TINYINT(1)', false, 1],
                'post_replies' => ['TINYINT(1)', false, 1],
                'post_topics'  => ['TINYINT(1)', false, 1],
            ],
            'PRIMARY KEY' => array('group_id', 'forum_id'),
        ];
        $this->c->DB->createTable('forum_perms', $schema);

        $schema = [
            'FIELDS' => [
                'id'              => ['SERIAL', false],
                'forum_name'      => ['VARCHAR(80)', false, 'New forum'],
                'forum_desc'      => ['TEXT', true],
                'redirect_url'    => ['VARCHAR(100)', true],
                'moderators'      => ['TEXT', true],
                'num_topics'      => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'num_posts'       => ['MEDIUMINT(8) UNSIGNED', false, 0],
                'last_post'       => ['INT(10) UNSIGNED', true],
                'last_post_id'    => ['INT(10) UNSIGNED', true],
                'last_poster'     => ['VARCHAR(200)', true],
                'last_topic'      => ['VARCHAR(255)', true],
                'sort_by'         => ['TINYINT(1)', false, 0],
                'disp_position'   => ['INT(10)', false, 0],
                'cat_id'          => ['INT(10) UNSIGNED', false, 0],
                'no_sum_mess'     => ['TINYINT(1)', false, 0],
                'parent_forum_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
        ];
        $this->c->DB->createTable('forums', $schema);

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
        ];
        $this->c->DB->createTable('groups', $schema);

        $schema = [
            'FIELDS' => [
                'user_id'     => ['INT(10) UNSIGNED', false, 1],
                'ident'       => ['VARCHAR(200)', false, ''],
                'logged'      => ['INT(10) UNSIGNED', false, 0],
                'idle'        => ['TINYINT(1)', false, 0],
                'last_post'   => ['INT(10) UNSIGNED', true],
                'last_search' => ['INT(10) UNSIGNED', true],
                'witt_data'   => ['VARCHAR(255)', false, ''],  //????
                'o_position'  => ['VARCHAR(100)', false, ''],
                'o_name'      => ['VARCHAR(200)', false, ''],
            ],
            'UNIQUE KEYS' => [
                'user_id_ident_idx' => ['user_id', 'ident(25)'],
            ],
            'INDEXES' => [
                'ident_idx'      => ['ident'],
                'logged_idx'     => ['logged'],
                'o_position_idx' => ['o_position'],
            ],
        ];
        $this->c->DB->createTable('online', $schema);

        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'poster'       => ['VARCHAR(200)', false, ''],
                'poster_id'    => ['INT(10) UNSIGNED', false, 1],
                'poster_ip'    => ['VARCHAR(39)', true],
                'poster_email' => ['VARCHAR(80)', true],
                'message'      => ['MEDIUMTEXT', true],
                'hide_smilies' => ['TINYINT(1)', false, 0],
                'edit_post'    => ['TINYINT(1)', false, 0],
                'posted'       => ['INT(10) UNSIGNED', false, 0],
                'edited'       => ['INT(10) UNSIGNED', true],
                'edited_by'    => ['VARCHAR(200)', true],
                'user_agent'   => ['VARCHAR(255)', true],
                'topic_id'     => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'topic_id_idx' => ['topic_id'],
                'multi_idx'    => ['poster_id', 'topic_id'],
            ],
        ];
        $this->c->DB->createTable('posts', $schema);

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
        ];
        $this->c->DB->createTable('reports', $schema);

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
        ];
        $this->c->DB->createTable('search_cache', $schema);

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
        ];
        $this->c->DB->createTable('search_matches', $schema);

        $schema = [
            'FIELDS' => [
                'id'   => ['SERIAL', false],
                'word' => ['VARCHAR(20)', false, '' , 'bin'],
            ],
            'PRIMARY KEY' => ['word'],
            'INDEXES' => [
                'id_idx' => ['id'],
            ],
        ];
        if ($v->dbtype == 'sqlite') { //????
            $schema['PRIMARY KEY'] = ['id'];
            $schema['UNIQUE KEYS'] = ['word_idx' => ['word']];
        }
        $this->c->DB->createTable('search_words', $schema);

        $schema = [
            'FIELDS' => [
                'user_id'  => ['INT(10) UNSIGNED', false, 0],
                'topic_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['user_id', 'topic_id'],
        ];
        $this->c->DB->createTable('topic_subscriptions', $schema);

        $schema = [
            'FIELDS' => [
                'user_id'  => ['INT(10) UNSIGNED', false, 0],
                'forum_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['user_id', 'forum_id'],
        ];
        $this->c->DB->createTable('forum_subscriptions', $schema);

        $schema = [
            'FIELDS' => [
                'id'            => ['SERIAL', false],
                'poster'        => ['VARCHAR(200)', false, ''],
                'subject'       => ['VARCHAR(255)', false, ''],
                'posted'        => ['INT(10) UNSIGNED', false, 0],
                'first_post_id' => ['INT(10) UNSIGNED', false, 0],
                'last_post'     => ['INT(10) UNSIGNED', false, 0],
                'last_post_id'  => ['INT(10) UNSIGNED', false, 0],
                'last_poster'   => ['VARCHAR(200)', true],
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
        ];
        $this->c->DB->createTable('topics', $schema);

        $schema = [
            'FIELDS' => [
                'bl_id'      => ['INT(10) UNSIGNED', false, 0],
                'bl_user_id' => ['INT(10) UNSIGNED', false, 0],
            ],
            'INDEXES' => [
                'bl_id_idx'      => ['bl_id'],
                'bl_user_id_idx' => ['bl_user_id']
            ],
        ];
        $this->c->DB->createTable('pms_new_block', $schema);

        $schema = [
            'FIELDS' => [
                'id'           => ['SERIAL', false],
                'poster'       => ['VARCHAR(200)', false, ''],
                'poster_id'    => ['INT(10) UNSIGNED', false, 1],
                'poster_ip'    => ['VARCHAR(39)', true],
                'message'      => ['TEXT', true],
                'hide_smilies' => ['TINYINT(1)', false, 0],
                'posted'       => ['INT(10) UNSIGNED', false, 0],
                'edited'       => ['INT(10) UNSIGNED', true],
                'edited_by'    => ['VARCHAR(200)', true],
                'post_new'     => ['TINYINT(1)', false, 1],
                'topic_id'     => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'topic_id_idx' => ['topic_id'],
                'multi_idx'    => ['poster_id', 'topic_id'],
            ],
        ];
        $this->c->DB->createTable('pms_new_posts', $schema);

        $schema = [
            'FIELDS' => [
                'id'          => ['SERIAL', false],
                'topic'       => ['VARCHAR(255)', false, ''],
                'starter'     => ['VARCHAR(200)', false, ''],
                'starter_id'  => ['INT(10) UNSIGNED', false, 0],
                'to_user'     => ['VARCHAR(200)', false, ''],
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
        ];
        $this->c->DB->createTable('pms_new_topics', $schema);

        $schema = [
            'FIELDS' => [
                'id'               => ['SERIAL', false],
                'group_id'         => ['INT(10) UNSIGNED', false, 3], //????
                'username'         => ['VARCHAR(200)', false, ''],
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
                'registration_ip'  => ['VARCHAR(39)', false, ''],
                'last_visit'       => ['INT(10) UNSIGNED', false, 0],
                'admin_note'       => ['VARCHAR(30)', true],
                'activate_string'  => ['VARCHAR(80)', true],
                'activate_key'     => ['VARCHAR(8)', true],
                'messages_enable'  => ['TINYINT(1)', false, 1],
                'messages_email'   => ['TINYINT(1)', false, 0],
                'messages_flag'    => ['TINYINT(1)', false, 0],
                'messages_new'     => ['INT(10) UNSIGNED', false, 0],
                'messages_all'     => ['INT(10) UNSIGNED', false, 0],
                'pmsn_last_post'   => ['INT(10) UNSIGNED', true],
                'warning_flag'     => ['TINYINT(1)', false, 0],
                'warning_all'      => ['INT(10) UNSIGNED', false, 0],
                'gender'           => ['TINYINT(4) UNSIGNED', false, 0],
                'u_mark_all_read'  => ['INT(10) UNSIGNED', true],
            ],
            'PRIMARY KEY' => ['id'],
            'UNIQUE KEYS' => [
                'username_idx' => ['username(25)'],
            ],
            'INDEXES' => [
                'registered_idx' => ['registered'],
            ],
        ];
        $this->c->DB->createTable('users', $schema);

        $schema = [
            'FIELDS' => [
                'id'            => ['SERIAL', false],
                'image'         => ['VARCHAR(40)', false, ''],
                'text'          => ['VARCHAR(20)', false, ''],
                'disp_position' => ['TINYINT(4) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['id'],
        ];
        $this->c->DB->createTable('smilies', $schema);

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

        $schema = [
            'FIELDS' => [
                'id'        => ['SERIAL', false],
                'poster'    => ['VARCHAR(200)', false, ''],
                'poster_id' => ['INT(10) UNSIGNED', false, 0],
                'posted'    => ['INT(10) UNSIGNED', false, 0],
                'message'   => ['TEXT', true],
            ],
            'PRIMARY KEY' => ['id'],
        ];
        $this->c->DB->createTable('warnings', $schema);

        $schema = [
            'FIELDS' => [
                'tid'      => ['INT(10) UNSIGNED', false, 0],
                'question' => ['TINYINT(4)', false, 0],
                'field'    => ['TINYINT(4)', false, 0],
                'choice'   => ['VARCHAR(255)', false, ''],
                'votes'    => ['INT(10) UNSIGNED', false, 0],
            ],
            'PRIMARY KEY' => ['tid', 'question', 'field'],
        ];
        $this->c->DB->createTable('poll', $schema);

        $schema = [
            'FIELDS' => [
                'tid' => ['INT(10) UNSIGNED', false],
                'uid' => ['INT(10) UNSIGNED', false],
                'rez' => ['TEXT', true],
            ],
            'PRIMARY KEY' => ['tid', 'uid'],
        ];
        $this->c->DB->createTable('poll_voted', $schema) ;

        $now = time();

        $groups = [
        // g_id, g_title,             g_user_title,        g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_mod_promote_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood
            [1, __('Administrators'), __('Administrator'), 0,           0,                0,                  0,                      0,               1,                   1,            1,            1,              1,             1,            1,              1,               1,           1,        1,              1,            0,            0,              0,             0],
            [2, __('Moderators'),     __('Moderator'),     1,           1,                1,                  1,                      1,               1,                   1,            1,            1,              1,             1,            1,              1,               1,           1,        1,              1,            0,            0,              0,             0],
            [3, __('Guests'),         NULL,                0,           0,                0,                  0,                      0,               0,                   1,            1,            0,              0,             0,            0,              0,               0,           1,        1,              0,            120,          60,             0,             0],
            [4, __('Members'),        NULL,                0,           0,                0,                  0,                      0,               0,                   1,            1,            1,              1,             1,            1,              1,               0,           1,        1,              1,            30,           30,             60,            60],
        ];
        foreach ($groups as $group) { //???? $db_type != 'pgsql'
            $this->c->DB->exec('INSERT INTO ::groups (g_id, g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_mod_promote_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood, g_report_flood) VALUES (?i, ?s, ?s, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i, ?i)', $group) ;
        }
        $this->c->DB->exec('UPDATE ::groups SET g_pm_limit=0 WHERE g_id=1') ;

        $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ?: 'unknow';
        $this->c->DB->exec('INSERT INTO ::users (group_id, username, password, email) VALUES (?i, ?s, ?s, ?s)', [3, __('Guest'), __('Guest'), __('Guest')]);
        $this->c->DB->exec('INSERT INTO ::users (group_id, username, password, email, language, style, num_posts, last_post, registered, registration_ip, last_visit) VALUES (?i, ?s, ?s, ?s, ?s, ?s, ?i, ?i, ?i, ?s, ?i)', [1, $v->username, password_hash($v->password, PASSWORD_DEFAULT), $v->email, $v->defaultlang, $v->defaultstyle, 1, $now, $now, $ip, $now]);

        $pun_config = [
            'i_fork_revision' => $this->c->FORK_REVISION,
            'o_board_title' => $v->title,
            'o_board_desc' => $v->descr,
            'o_default_timezone' => 0,
            'o_time_format' => 'H:i:s',
            'o_date_format' => 'Y-m-d',
            'o_timeout_visit' => 1800,
            'o_timeout_online' => 300,
            'o_redirect_delay' => 1,
            'o_show_version' => 0,
            'o_show_user_info' => 1,
            'o_show_post_count' => 1,
            'o_signatures' => 1,
            'o_smilies' => 1,
            'o_smilies_sig' => 1,
            'o_make_links' => 1,
            'o_default_lang' => $v->defaultlang,
            'o_default_style' => $v->defaultstyle,
            'o_default_user_group' => 4,
            'o_topic_review' => 15,
            'o_disp_topics_default' => 30,
            'o_disp_posts_default' => 25,
            'o_indent_num_spaces' => 4,
            'o_quote_depth' => 3,
            'o_quickpost' => 1,
            'o_users_online' => 1,
            'o_censoring' => 0,
            'o_show_dot' => 0,
            'o_topic_views' => 1,
            'o_quickjump' => 1,
            'o_gzip' => 0,
            'o_additional_navlinks' => '',
            'o_report_method' => 0,
            'o_regs_report' => 0,
            'o_default_email_setting' => 1,
            'o_mailing_list' => $v->email,
            'o_avatars' => in_array(strtolower(@ini_get('file_uploads')), ['on', 'true', '1']) ? 1 : 0,
            'o_avatars_dir' => 'img/avatars',
            'o_avatars_width' => 60,
            'o_avatars_height' => 60,
            'o_avatars_size' => 10240,
            'o_search_all_forums' => 1,
            'o_admin_email' => $v->email,
            'o_webmaster_email' => $v->email,
            'o_forum_subscriptions' => 1,
            'o_topic_subscriptions' => 1,
            'o_smtp_host' => NULL,
            'o_smtp_user' => NULL,
            'o_smtp_pass' => NULL,
            'o_smtp_ssl' => 0,
            'o_regs_allow' => 1,
            'o_regs_verify' => 0,
            'o_announcement' => 0,
            'o_announcement_message' => __('Announcement'),
            'o_rules' => 0,
            'o_rules_message' => __('Rules'),
            'o_maintenance' => 0,
            'o_maintenance_message' => __('Maintenance message'),
            'o_default_dst' => 0,
            'o_feed_type' => 2,
            'o_feed_ttl' => 0,
            'p_message_bbcode' => 1,
            'p_message_img_tag' => 1,
            'p_message_all_caps' => 1,
            'p_subject_all_caps' => 1,
            'p_sig_all_caps' => 1,
            'p_sig_bbcode' => 1,
            'p_sig_img_tag' => 0,
            'p_sig_length' => 400,
            'p_sig_lines' => 4,
            'p_allow_banned_email' => 1,
            'p_allow_dupe_email' => 0,
            'p_force_guest_email' => 1,
            'o_pms_enabled' => 1,                    // New PMS - Visman
            'o_pms_min_kolvo' => 0,
            'o_merge_timeout' => 86400,        // merge post - Visman
            'o_board_redirect' => '',    // для редиректа - Visman
            'o_board_redirectg' => 0,
            'o_poll_enabled' => 0,    // опросы - Visman
            'o_poll_max_ques' => 3,
            'o_poll_max_field' => 20,
            'o_poll_time' => 60,
            'o_poll_term' => 3,
            'o_poll_guest' => 0,
            'o_fbox_guest' => 0,    // Fancybox - Visman
            'o_fbox_files' => 'viewtopic.php,search.php,pmsnew.php',
            'o_coding_forms' => 1,    // кодирование форм - Visman
            'o_check_ip' => 0,    // проверка ip администрации - Visman
            'o_crypto_enable' => 1,    // случайные имена полей форм - Visman
            'o_crypto_pas' => $this->c->Secury->randomPass(25),
            'o_crypto_salt' => $this->c->Secury->randomPass(13),
            'o_enable_acaptcha' => 1, // математическая каптча
            'st_max_users' => 1,    // статистика по максимуму юзеров - Visman
            'st_max_users_time' => time(),
        ];

        foreach ($pun_config as $conf_name => $conf_value) {
            $this->c->DB->exec('INSERT INTO ::config (conf_name, conf_value) VALUES (?s, ?s)', [$conf_name, $conf_value]);
        }

        // Insert some other default data
        $subject = __('Test post');
        $message = __('Test message');

        $this->c->DB->exec('INSERT INTO ::categories (cat_name, disp_position) VALUES (?s, ?i)', [__('Test category'), 1]);

        $this->c->DB->exec('INSERT INTO ::forums (forum_name, forum_desc, num_topics, num_posts, last_post, last_post_id, last_poster, last_topic, disp_position, cat_id) VALUES (?s, ?s, ?i, ?i, ?i, ?i, ?s, ?s, ?i, ?i)', [__('Test forum'), __('This is just a test forum'), 1, 1, $now, 1, $v->username, $subject, 1, 1]);

        $this->c->DB->exec('INSERT INTO ::topics (poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, forum_id) VALUES(?s, ?s, ?i, ?i, ?i, ?i, ?s, ?i)', [$v->username, $subject, $now, 1, $now, 1, $v->username, 1]);

        $this->c->DB->exec('INSERT INTO ::posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES(?s, ?i, ?s, ?s, ?i, ?i)', [$v->username, 2, $ip, $message, $now, 1]);

        $this->c->DB->commit();

        exit();
    }
}
