<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Config as CoreConfig;
use ForkBB\Core\Container;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use PDO;
use PDOException;
use RuntimeException;
use ForkBB\Core\Exceptions\ForkException;
use function \ForkBB\__;

class Update extends Admin
{
    const PHP_MIN                    = '8.0.0';
    const REV_MIN_FOR_UPDATE         = 53;
    const LATEST_REV_WITH_DB_CHANGES = 87;
    const LOCK_NAME                  = 'lock_update';
    const LOCK_TTL                   = 1800;
    const CONFIG_FILE                = 'main.php';

    protected string $configFile;

    /**
     * Флаг проверки пароля
     */
    protected bool $okPass;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $container->Lang->load('validator');
        $container->Lang->load('admin_update');

        $this->aIndex     = 'update';
        $this->httpStatus = 503;
        $this->onlinePos  = null;
        $this->nameTpl    = 'admin/form';
        $this->titleForm  = 'Update ForkBB';
        $this->classForm  = ['updateforkbb'];
        $this->configFile = $container->DIR_CONFIG . '/' . self::CONFIG_FILE;

        $this->header('Retry-After', '3600');
    }

    /**
     * Подготовка страницы к отображению
     */
    public function prepare(): void
    {
        $this->aNavigation = $this->aNavigation();
        $this->crumbs      = $this->crumbs(...$this->aCrumbs);
    }

    /**
     * Возвращает массив ссылок с описанием для построения навигации админки
     */
    protected function aNavigation(): array
    {
        return [
            'update' => [
                $this->c->Router->link('AdminUpdate'),
                __('Update ForkBB'),
            ],
        ];
    }

    /**
     * Возвращает страницу обслуживания с доп.сообщением
     */
    protected function returnMaintenance(bool $isStage = true): Page
    {
        $maintenance = $this->c->Maintenance;
        $maintenance->fIswev = [FORK_MESS_WARN, 'Update script is running'];

        if ($isStage) {
            $maintenance->fIswev = [FORK_MESS_ERR, 'Script runs error'];
        }

        return $maintenance;
    }

    /**
     * Проверяет наличие блокировки скрипта обновления
     */
    protected function hasLock(?string $uid = null): bool
    {
        $lock = $this->c->Cache->get(self::LOCK_NAME);

        if (null === $uid) {
            return ! empty($lock);

        } else {
            return empty($lock) || ! \hash_equals($uid, (string) $lock);
        }
    }

    protected function setLock(?string $uid = null): ?string
    {
        if (true === $this->hasLock($uid)) {
            return null;
        }

        if (null === $uid) {
            $uid = $this->c->Secury->randomHash(33);
        }

        $this->c->Cache->set(self::LOCK_NAME, $uid, self::LOCK_TTL);

        if (true === $this->hasLock($uid)) {
            return null;
        }

        return $uid;
    }

    /**
     * Подготавливает данные для страницы обновления форума
     */
    public function view(array $args, string $method): Page
    {
        if (true === $this->hasLock()) {
            return $this->returnMaintenance(false);
        }

        if (
            'POST' === $method
            && empty($this->fIswev)
        ) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_pass' => [$this, 'vCheckPass'],
                ])->addRules([
                    'token'                 => 'token:AdminUpdate',
                    'dbpass'                => 'exist|string|check_pass',
                    'o_maintenance_message' => 'required|string:trim|max:65000 bytes|html',
                ])->addAliases([
                    'dbpass'                => 'Database password',
                    'o_maintenance_message' => 'Maintenance message',
                ])->addMessages([
                ]);

            if (
                $v->validation($_POST)
                && $this->okPass
            ) {
                $e = null;

                // версия PHP
                if (
                    null === $e
                    && \version_compare(\PHP_VERSION, self::PHP_MIN, '<')
                ) {
                    $e = __(['You are running error', 'PHP', \PHP_VERSION, $this->c->FORK_REVISION, self::PHP_MIN]);
                }

                // база не от ForkBB или старая ревизия
                if (
                    null === $e
                    && $this->c->config->i_fork_revision < self::REV_MIN_FOR_UPDATE
                ) {
                    $e = 'Version mismatch error';
                }

                // загрузка и проверка конфига
                if (null === $e) {
                    try {
                        $coreConfig = new CoreConfig($this->configFile);
                    } catch (ForkException $excp) {
                        $e = $excp->getMessage();
                    }
                }

                // проверка доступности базы данных на изменения
                if (
                    null === $e
                    && $this->c->config->i_fork_revision < self::LATEST_REV_WITH_DB_CHANGES
                ) {
                    $testTable = '::test_tb_for_update';

                    if (
                        null === $e
                        && true === $this->c->DB->tableExists($testTable)
                    ) {
                        $e = ['The %s table already exists. Delete it.', $testTable];
                    }

                    $schema = [
                        'FIELDS' => [
                            'id' => ['SERIAL', false],
                        ],
                        'PRIMARY KEY' => ['id'],
                    ];

                    if (
                        null === $e
                        && false === $this->c->DB->createTable($testTable, $schema)
                    ) {
                        $e = ['Unable to create %s table', $testTable];
                    }

                    if (
                        null === $e
                        && false === $this->c->DB->addField($testTable, 'test_field', 'VARCHAR(80)', false, '')
                    ) {
                        $e = ['Unable to add test_field field to %s table', $testTable];
                    }

                    $sql = "INSERT INTO {$testTable} (test_field) VALUES ('TEST_VALUE')";

                    if (
                        null === $e
                        && false === $this->c->DB->exec($sql)
                    ) {
                        $e = ['Unable to insert line to %s table', $testTable];
                    }

                    if (
                        null === $e
                        && false === $this->c->DB->dropField($testTable, 'test_field')
                    ) {
                        $e = ['Unable to drop test_field field from %s table', $testTable];
                    }

                    if (
                        null === $e
                        && false === $this->c->DB->dropTable($testTable)
                    ) {
                        $e = ['Unable to drop %s table', $testTable];
                    }
                }

                if (null !== $e) {
                    return $this->c->Message->message($e, true, 503);
                }

                $uid = $this->setLock();

                if (null === $uid) {
                    $this->fIswev = [FORK_MESS_ERR, 'Unable to write update lock'];

                } else {
                    $this->c->config->o_maintenance_message = $v->o_maintenance_message;

                    $this->c->config->save();

                    return $this->c->Redirect->page('AdminUpdateStage', ['uid' => $uid, 'stage' => 1]);
                }

            } else {
                $this->fIswev = $v->getErrors();
            }

        } else {
            $v = null;
        }

        $this->form = $this->form($v);

        return $this;
    }

    /**
     * Проверяет пароль базы
     */
    public function vCheckPass(Validator $v, string $dbpass): string
    {
        $this->okPass = true;

        if (\str_starts_with($this->c->DB_DSN, 'sqlite')) {
            if (! \hash_equals($this->c->DB_DSN, "sqlite:{$dbpass}")) {
                $this->okPass = false;

                $v->addError(['Invalid file error', self::CONFIG_FILE]);
            }

        } else {
            if (! \hash_equals($this->c->DB_PASSWORD, $dbpass)) {
                $this->okPass = false;

                $v->addError(['Invalid password error', self::CONFIG_FILE]);
            }
        }

        return $dbpass;
    }

    /**
     * Формирует массив для формы
     */
    protected function form(?Validator $v): array
    {
        return [
            'action' => $this->c->Router->link('AdminUpdate'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUpdate'),
            ],
            'sets'   => [
                'update-info' => [
                    'inform' => [
                        [
                            'message' => 'Update message',
                        ],
                    ],
                ],
                'update' => [
                    'legend' => 'Update ForkBB',
                    'fields' => [
                        'dbpass' => [
                            'type'     => 'password',
                            'value'    => '',
                            'caption'  => 'Database password',
                            'help'     => 'Database password note',
                        ],
                        'o_maintenance_message' => [
                            'type'     => 'textarea',
                            'value'    => $v->o_maintenance_message ?? $this->c->config->o_maintenance_message,
                            'caption'  => 'Maintenance message',
                            'help'     => 'Maintenance message info',
                            'required' => true,
                        ],
                    ],
                ],
                'member-info' => [
                    'inform' => [
                        [
                            'message' => 'Members message',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'start' => [
                    'type'  => 'submit',
                    'value' => __('Start update'),
                ],
            ],
        ];
    }

    /**
     * Обновляет форум
     */
    public function stage(array $args, string $method): Page
    {
        try {
            $uid = $this->setLock($args['uid']);

            if (null === $uid) {
                return $this->returnMaintenance();
            }

            $stage = \max($args['stage'], $this->c->config->i_fork_revision);

            do {
                if (\method_exists($this, 'stageNumber' . $stage)) {
                    if (\function_exists('\\set_time_limit')) {
                        \set_time_limit(0);
                    }

                    $start = $this->{'stageNumber' . $stage}($args);

                    if (null === $start) {
                        ++$stage;
                    }

                    return $this->c->Redirect->page(
                        'AdminUpdateStage',
                        ['uid' => $uid, 'stage' => $stage, 'start' => $start]
                    )->message(['Stage %1$s (%2$s)', $stage, (int) $start], FORK_MESS_SUCC);
                }

                ++$stage;
            } while ($stage < $this->c->FORK_REVISION);

            $this->c->config->i_fork_revision = $this->c->FORK_REVISION;

            $this->c->config->save();

            if (true !== $this->c->Cache->clear()) {
                throw new RuntimeException('Unable to clear cache');
            }

            return $this->c->Redirect->page('Index')->message('Successfully updated', FORK_MESS_SUCC);
        } catch (ForkException $excp) {
            return $this->c->Message->message($excp->getMessage(), true, 503);
        }
    }

#    /**
#     * Выполняет определенный шаг обновления
#     *
#     * Возвращает null, если шаг выпонен
#     * Возвращает положительный int, если требуется продолжить выполнение шага
#     */
#    protected function stageNumber1(array $args): ?int
#    {
#        $coreConfig = new CoreConfig($this->configFile);
#
#        $coreConfig->add(
#            'multiple=>AdminUsersRecalculate',
#            '\\ForkBB\\Models\\Pages\\Admin\\Users\\Recalculate::class',
#            'AdminUsersNew'
#        );
#
#        $coreConfig->save();
#
#        return null;
#    }

    /**
     * rev.54 to rev.55
     */
    protected function stageNumber54(array $args): ?int
    {
        $config = $this->c->config;

        $config->b_oauth_allow = 0;

        $config->save();

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

        $providers = [
            'github',
        ];

        $query = 'INSERT INTO ::providers (pr_name, pr_pos)
            SELECT tmp.*
            FROM (SELECT ?s:name AS f1, ?i:pos AS f2) AS tmp
            WHERE NOT EXISTS (
                SELECT 1
                FROM ::providers
                WHERE pr_name=?s:name
            )';

        foreach ($providers as $pos => $name) {
            $vars = [
                ':name' => $name,
                ':pos'  => $pos,
            ];

            $this->c->DB->exec($query, $vars);
        }

        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>AdminProviders',
            '\\ForkBB\\Models\\Pages\\Admin\\Providers::class',
            'AdminOptions'
        );
        $coreConfig->add(
            'shared=>providers',
            [
                'class'   => '\\ForkBB\\Models\\Provider\\Providers::class',
                'drivers' => [
                    'github' => '\\ForkBB\\Models\\Provider\\Driver\\GitHub::class'
                ],
            ],
            'pms'
        );
        $coreConfig->add(
            'multiple=>RegLog',
            '\\ForkBB\\Models\\Pages\\RegLog::class',
            'Register'
        );
        $coreConfig->add(
            'shared=>providerUser',
            '\\ForkBB\\Models\\ProviderUser\\ProviderUser::class',
            'providers'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.55 to rev.56
     */
    protected function stageNumber55(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>ProfileOAuth',
            '\\ForkBB\\Models\\Pages\\Profile\\OAuth::class',
            'ProfileMod'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.56 to rev.57
     */
    protected function stageNumber56(array $args): ?int
    {
        $providers = [
            1 => 'yandex',
            2 => 'google',
        ];

        $query = 'INSERT INTO ::providers (pr_name, pr_pos)
            SELECT tmp.*
            FROM (SELECT ?s:name AS f1, ?i:pos AS f2) AS tmp
            WHERE NOT EXISTS (
                SELECT 1
                FROM ::providers
                WHERE pr_name=?s:name
            )';

        foreach ($providers as $pos => $name) {
            $vars = [
                ':name' => $name,
                ':pos'  => $pos,
            ];

            $this->c->DB->exec($query, $vars);
        }

        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>providers=>drivers=>yandex',
            '\\ForkBB\\Models\\Provider\\Driver\\Yandex::class'
        );
        $coreConfig->add(
            'shared=>providers=>drivers=>google',
            '\\ForkBB\\Models\\Provider\\Driver\\Google::class'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.57 to rev.58
     */
    protected function stageNumber57(array $args): ?int
    {
        $config = $this->c->config;

        $config->i_avatars_quality = 75;

        $config->save();

        $queryI  = 'INSERT INTO ::bbcode (bb_tag, bb_edit, bb_delete, bb_structure)
            VALUES(?s:tag, 1, 0, ?s:structure)';
        $queryU  = 'UPDATE ::bbcode
            SET bb_edit=1, bb_delete=0, bb_structure=?s:structure
            WHERE bb_tag=?s:tag';
        $bbcodes = include $this->c->DIR_CONFIG . '/defaultBBCode.php';

        foreach ($bbcodes as $bbcode) {
            $vars  = [
                ':tag'       => $bbcode['tag'],
                ':structure' => \json_encode($bbcode, FORK_JSON_ENCODE),
            ];
            $exist = $this->c->DB->query('SELECT 1 FROM ::bbcode WHERE bb_tag=?s:tag', $vars)->fetchColumn();
            $query = empty($exist) ? $queryI : $queryU;

            $this->c->DB->exec($query, $vars);
        }

        return null;
    }

    /**
     * rev.58 to rev.59
     */
    protected function stageNumber58(array $args): ?int
    {
        $config = $this->c->config;

        $config->b_upload                = 0;
        $config->i_upload_img_quality    = 75;
        $config->i_upload_img_axis_limit = 1920;

        $config->save();

        $this->c->DB->addField('::groups', 'g_up_ext', 'VARCHAR(255)', false, 'webp,jpg,jpeg,png,gif,avif');
        $this->c->DB->addField('::groups', 'g_up_size_kb', 'INT(10) UNSIGNED', false, 0);
        $this->c->DB->addField('::groups', 'g_up_limit_mb', 'INT(10) UNSIGNED', false, 0);

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
        ];
        $this->c->DB->createTable('::attachments_pos_pm', $schema);

        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>AdminUploads',
            '\\ForkBB\\Models\\Pages\\Admin\\Uploads::class',
            'AdminLogs'
        );

        $coreConfig->add(
            'shared=>attachments',
            '\\ForkBB\\Models\\Attachment\\Attachments::class',
            'providerUser'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.59 to rev.60
     */
    protected function stageNumber59(array $args): ?int
    {
        $queryI  = 'INSERT INTO ::bbcode (bb_tag, bb_edit, bb_delete, bb_structure)
            VALUES(?s:tag, 1, 0, ?s:structure)';
        $queryU  = 'UPDATE ::bbcode
            SET bb_edit=1, bb_delete=0, bb_structure=?s:structure
            WHERE bb_tag=?s:tag';
        $bbcodes = include $this->c->DIR_CONFIG . '/defaultBBCode.php';

        foreach ($bbcodes as $bbcode) {
            if ('hashtag' !== $bbcode['tag']) {
                continue;
            }

            $vars  = [
                ':tag'       => $bbcode['tag'],
                ':structure' => \json_encode($bbcode, FORK_JSON_ENCODE),
            ];
            $exist = $this->c->DB->query('SELECT 1 FROM ::bbcode WHERE bb_tag=?s:tag', $vars)->fetchColumn();
            $query = empty($exist) ? $queryI : $queryU;

            $this->c->DB->exec($query, $vars);
        }

        return null;
    }

    /**
     * rev.60 to rev.61
     */
    protected function stageNumber60(array $args): ?int
    {
        $config = $this->c->config;

        $config->s_upload_img_outf = 'webp,jpg,png,gif';
        $config->i_search_ttl      = 900;

        $config->save();

        $this->c->DB->addField('::users', 'u_up_size_mb', 'INT(10) UNSIGNED', false, 0);

        return null;
    }

    /**
     * rev.61 to rev.62
     */
    protected function stageNumber61(array $args): ?int
    {
        $this->c->DB->dropIndex('::posts', 'multi_idx');
        $this->c->DB->addIndex('::posts', 'multi_idx', ['poster_id', 'topic_id', 'posted']);

        $this->c->DB->dropIndex('::search_matches', 'word_id_idx');
        $this->c->DB->addIndex('::search_matches', 'multi_idx', ['word_id', 'post_id']);

        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'USERNAME',
            [
                'phpPattern' => '\'%^\\p{L}[\\p{L}\\p{N}\\x20\\._-]+$%uD\'',
                'jsPattern'  => '\'^.{2,}$\'',
                'min'        => '2',
                'max'        => '25',
            ],
            'FLOOD_INTERVAL'
        );
        $coreConfig->delete('USERNAME_PATTERN');

        $coreConfig->save();

        return null;
    }

    /**
     * rev.62 to rev.63
     */
    protected function stageNumber62(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>ProfileDelete',
            '\\ForkBB\\Models\\Pages\\Profile\\Delete::class',
            'ProfileOAuth'
        );

        $coreConfig->save();

        $this->c->DB->addField('::groups', 'g_delete_profile', 'TINYINT(1)', false, 0);

        return null;
    }

    /**
     * rev.63 to rev.64
     */
    protected function stageNumber63(array $args): ?int
    {
        $config = $this->c->config;

        $config->b_ant_hidden_ch = 1;
        $config->b_ant_use_js    = 0;

        $config->save();

        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>AdminAntispam',
            '\\ForkBB\\Models\\Pages\\Admin\\Antispam::class',
            'AdminUploads'
        );

        $coreConfig->add(
            'shared=>VLnekot',
            '\\ForkBB\\Models\\Validators\\Nekot::class',
            'VLhtml'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.64 to rev.65
     */
    protected function stageNumber64(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'MAX_SUBJ_LENGTH',
            '70',
            'MAX_POST_SIZE'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.65 to rev.66
     */
    protected function stageNumber65(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>Test',
            [
                'class'  => '\\ForkBB\\Core\\Test::class',
                'config' => '\'%DIR_CONFIG%/test.default.php\'',
            ]
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.66 to rev.67
     */
    protected function stageNumber66(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>ProfileSearch',
            '\\ForkBB\\Models\\Pages\\Profile\\Search::class',
            'ProfileDelete'
        );

        $coreConfig->save();

        $this->c->DB->addField('::users', 'unfollowed_f', 'VARCHAR(255)', false, '');

        return null;
    }

    /**
     * rev.67 to rev.68
     */
    protected function stageNumber67(array $args): ?int
    {
        $config = $this->c->config;

        $config->s_meta_desc ??= '';
        $config->a_og_image  ??= [];

        $config->save();

        $this->c->DB->addIndex('::posts', 'editor_id_idx', ['editor_id']);

        $this->c->DB->dropIndex('::topics', 'forum_id_idx');
        $this->c->DB->dropIndex('::topics', 'moved_to_idx');
        $this->c->DB->addIndex('::topics', 'multi_1_idx', ['moved_to', 'forum_id', 'num_replies', 'last_post']);
        $this->c->DB->addIndex('::topics', 'multi_2_idx', ['forum_id', 'sticky', 'last_post']);

        return null;
    }

    /**
     * rev.68 to rev.69
     */
    protected function stageNumber68(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>View',
            [
                'class'  => '\\ForkBB\\Core\\View::class',
                'config' => [
                    'cache'      => '\'%DIR_CACHE%\'',
                    'defaultDir' => '\'%DIR_VIEWS%/_default\'',
                    'userDir'    => '\'%DIR_VIEWS%/_user\'',
                ],
            ]
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.69 to rev.70
     */
    protected function stageNumber69(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>%DIR_EXT%',
            '\'%DIR_ROOT%/ext\'',
            '%DIR_VIEWS%'
        );

        $coreConfig->add(
            'multiple=>ExtensionModel',
            '\\ForkBB\\Models\\Extension\\Extension::class',
            'DBMapModel'
        );

        $coreConfig->add(
            'multiple=>ExtensionManager',
            '\\ForkBB\\Models\\Extension\\Extensions::class',
            'ExtensionModel'
        );

        $coreConfig->add(
            'shared=>extensions',
            '\'@ExtensionManager:init\'',
            'attachments'
        );

        $coreConfig->add(
            'multiple=>AdminExtensions',
            '\\ForkBB\\Models\\Pages\\Admin\\Extensions::class',
            'AdminAntispam'
        );

        $coreConfig->add(
            'shared=>View=>config=>preFile',
            '\'%DIR_CONFIG%/ext/pre.php\''
        );

        $coreConfig->save();

        // extensions
        $schema = [
            'FIELDS' => [
                'ext_name'   => ['VARCHAR(190)', false, ''],
                'ext_status' => ['TINYINT', false, 0],
                'ext_data'   => ['TEXT', false],
            ],
            'PRIMARY KEY' => ['ext_name'],
        ];
        $this->c->DB->createTable('::extensions', $schema);

        return null;
    }

    /**
     * rev.70 to rev.71
     */
    protected function stageNumber70(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'FRIENDLY_URL',
            [
                'lowercase' => 'false',
                'translit'  => '\'\'',
                'WtoHyphen' => 'false',
            ],
            'TIME_FORMATS'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.71 to rev.72
     */
    protected function stageNumber71(array $args): ?int
    {
        switch ($args['start'] ?? 1) {
            case 3:
                $f = $this->c->FRIENDLY_URL;

                if (
                    ! empty($f['lowercase'])
                    || ! empty($f['translit'])
                    || ! empty($f['WtoHyphen'])
                ) {
                    $names = $this->c->DB->query('SELECT id, forum_name FROM ::forums WHERE redirect_url=\'\' ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
                    $query = 'UPDATE ::forums SET friendly_name=?s:name WHERE id=?i:id';

                    foreach ($names as $id => $name) {
                        $vars = [
                            ':id'   => $id,
                            ':name' => \mb_substr($this->c->Func->friendly($name), 0, 80, 'UTF-8'),
                        ];

                        $this->c->DB->exec($query, $vars);
                    }
                }

                return null;
            case 2:
                $this->c->DB->addField('::forums', 'friendly_name', 'VARCHAR(80)', false, '', null, 'forum_name');

                return 3;
            default:
                $coreConfig = new CoreConfig($this->configFile);

                $coreConfig->add(
                    'FRIENDLY_URL=>file',
                    '\'translit.default.php\'',
                    'WtoHyphen'
                );

                $coreConfig->save();

                return 2;
        }
    }

    /**
     * rev.72 to rev.73
     */
    protected function stageNumber72(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>Sitemap',
            '\\ForkBB\\Models\\Pages\\Sitemap::class',
            'Misc'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.73 to rev.74
     */
    protected function stageNumber73(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>Mail=>file',
            '\'%DIR_CONFIG%/domains.default.php\'',
            'eol'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.75 to rev.76
     */
    protected function stageNumber75(array $args): ?int
    {
        $this->c->DB->addField('::groups', 'g_use_reaction', 'TINYINT(1)', false, 1);
        $this->c->DB->addField('::posts', 'reactions', 'VARCHAR(255)', false, '');
        $this->c->DB->addField('::users', 'show_reaction', 'TINYINT(1)', false, 1);

        $this->c->DB->query('UPDATE ::groups SET g_use_reaction=0 WHERE g_id=?i', [FORK_GROUP_GUEST]);

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
        ];
        $this->c->DB->createTable('::reactions', $schema);

        $config = $this->c->config;

        $config->b_reaction       = 0;
        $config->a_reaction_types = [
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
        ];

        $config->save();

        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>Reaction',
            '\\ForkBB\\Models\\Pages\\Reaction::class',
            'ProfileSearch'
        );

        $coreConfig->add(
            'shared=>reactions',
            '\\ForkBB\\Models\\Reaction\\Reactions::class',
            'extensions'
        );

        $coreConfig->add(
            'shared=>Reactions/delete',
            '\\ForkBB\\Models\\Reaction\\Delete::class'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.76 to rev.77
     */
    protected function stageNumber76(array $args): ?int
    {
        $config = $this->c->config;

        $config->b_show_user_reaction ??= 0;

        $config->save();

        $this->c->DB->addField('::users', 'page_scroll', 'TINYINT', false, 0);

        return null;
    }

    /**
     * rev.77 to rev.78
     */
    protected function stageNumber77(array $args): ?int
    {
        $this->c->DB->alterField('::forums', 'num_topics', 'INT(10) UNSIGNED', false, 0);
        $this->c->DB->alterField('::forums', 'num_posts', 'INT(10) UNSIGNED', false, 0);
        $this->c->DB->alterField('::topics', 'num_views', 'INT(10) UNSIGNED', false, 0);
        $this->c->DB->alterField('::topics', 'num_replies', 'INT(10) UNSIGNED', false, 0);

        $this->c->DB->addField('::topics', 'solution', 'INT(10) UNSIGNED', false, 0);
        $this->c->DB->addField('::topics', 'solution_wa', 'VARCHAR(190)', false, '');
        $this->c->DB->addField('::topics', 'solution_wa_id', 'INT(10) UNSIGNED', false, 0);
        $this->c->DB->addField('::topics', 'solution_time', 'INT(10) UNSIGNED', false, 0);

        $this->c->DB->addField('::forums', 'use_solution', 'TINYINT(1)', false, 0);

        return null;
    }

    /**
     * rev.78 to rev.79
     */
    protected function stageNumber78(array $args): ?int
    {
        $queryI  = 'INSERT INTO ::bbcode (bb_tag, bb_edit, bb_delete, bb_structure)
            VALUES(?s:tag, 1, 0, ?s:structure)';
        $queryU  = 'UPDATE ::bbcode
            SET bb_edit=1, bb_delete=0, bb_structure=?s:structure
            WHERE bb_tag=?s:tag';
        $bbcodes = include $this->c->DIR_CONFIG . '/defaultBBCode.php';

        $tagsUpd = ['b', 'i', 'em', 'u', 's', 'del', 'ins', 'sub', 'sup', 'h', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'color', 'colour', 'background', 'font'];
        $tagsUpd = \array_flip($tagsUpd);

        foreach ($bbcodes as $bbcode) {
            if (! isset($tagsUpd[$bbcode['tag']])) {
                continue;
            }

            $vars = [
                ':tag'       => $bbcode['tag'],
                ':structure' => \json_encode($bbcode, FORK_JSON_ENCODE),
            ];
            $exist = $this->c->DB->query('SELECT 1 FROM ::bbcode WHERE bb_tag=?s:tag', $vars)->fetchColumn();
            $query = empty($exist) ? $queryI : $queryU;

            $this->c->DB->exec($query, $vars);
        }

        $this->c->DB->addField('::topics', 'toc', 'TEXT', true);

        return null;
    }

    /**
     * rev.79 to rev.80
     */
    protected function stageNumber79(array $args): ?int
    {
        $config = $this->c->config;

        $config->b_default_lang_auto ??= 1;

        $config->save();

        return null;
    }

    /**
     * rev.80 to rev.81
     */
    protected function stageNumber80(array $args): ?int
    {
        $this->c->DB->addField('::forums', 'use_custom_fields', 'TINYINT(1)', false, 0);
        $this->c->DB->addField('::forums', 'custom_fields', 'TEXT', true);
        $this->c->DB->addField('::topics', 'cf_level', 'TINYINT UNSIGNED', false, 0);
        $this->c->DB->addField('::topics', 'cf_data', 'TEXT', true);

        return null;
    }

    /**
     * rev.81 to rev.82
     */
    protected function stageNumber81(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>ProfileAboutMe',
            '\\ForkBB\\Models\\Pages\\Profile\\AboutMe::class',
            'ProfileSearch'
        );

        $coreConfig->save();

        $this->c->DB->addField('::users', 'about_me_id', 'INT(10) UNSIGNED', false, 0);
        $this->c->DB->addField('::groups', 'g_use_about_me', 'TINYINT(1)', false, 1);

        $now     = \time();
        $ip      = \filter_var($_SERVER['REMOTE_ADDR'], \FILTER_VALIDATE_IP) ?: '0.0.0.0';
        $topicId = 1;

        $this->c->DB->exec('INSERT INTO ::posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES(?s, ?i, ?s, ?s, ?i, ?i)', ['ForkBB', 0, $ip, 'Start', $now, $topicId]);

        $postId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('INSERT INTO ::topics (poster, poster_id, subject, posted, first_post_id, last_post, last_post_id, last_poster, last_poster_id, forum_id) VALUES(?s, ?i, ?s, ?i, ?i, ?i, ?i, ?s, ?i, ?i)', ['ForkBB', 0, '[system] About me', $now, $postId, $now, $postId, 'ForkBB', 0, FORK_SFID]);

        $topicId = (int) $this->c->DB->lastInsertId();

        $this->c->DB->exec('UPDATE ::posts SET topic_id=?i WHERE id=?i', [$topicId, $postId]);

        $config = $this->c->config;

        $config->i_about_me_topic_id = $topicId;

        $config->save();

        return null;
    }

    /**
     * rev.82 to rev.83
     */
    protected function stageNumber82(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->delete('DATE_FORMATS');
        $coreConfig->delete('TIME_FORMATS');

        $coreConfig->save();

        $this->c->DB->addField('::users', 'locale', 'VARCHAR(20)', false, 'en', null, 'language');

        return null;
    }

    /**
     * rev.84 to rev.85
     */
    protected function stageNumber84(array $args): ?int
    {
        $queryU  = 'UPDATE ::bbcode
            SET bb_structure=?s:structure
            WHERE bb_tag=?s:tag';
        $bbcodes = include $this->c->DIR_CONFIG . '/defaultBBCode.php';

        $tagsUpd = ['h', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'left', 'right', 'center', 'justify'];
        $tagsUpd = \array_flip($tagsUpd);

        foreach ($bbcodes as $bbcode) {
            if (! isset($tagsUpd[$bbcode['tag']])) {
                continue;
            }

            $vars = [
                ':tag'       => $bbcode['tag'],
                ':structure' => \json_encode($bbcode, FORK_JSON_ENCODE),
            ];

            $this->c->DB->exec($queryU, $vars);
        }

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
                'form_data'    => ['MEDIUMTEXT', false],
            ],
            'PRIMARY KEY' => ['id'],
            'INDEXES' => [
                'poster_id_idx' => ['poster_id'],
                'multi1_idx'    => ['topic_id', 'poster_id'],
                'multi2_idx'    => ['forum_id', 'poster_id'],
            ],
        ];
        $this->c->DB->createTable('::drafts', $schema);

        $this->c->DB->addField('::users', 'num_drafts', 'INT(10) UNSIGNED', false, 0, 'num_topics');

        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>drafts',
            '\\ForkBB\\Models\\Draft\\Drafts::class',
            'posts'
        );

        $coreConfig->add(
            'multiple=>DraftModel',
            '\\ForkBB\\Models\\Draft\\Draft::class',
            'PostModel'
        );

        $coreConfig->add(
            'shared=>Drafts/load',
            '\\ForkBB\\Models\\Draft\\Load::class',
            'Posts/view'
        );

        $coreConfig->add(
            'shared=>Drafts/save',
            '\\ForkBB\\Models\\Draft\\Save::class',
            'Drafts/load'
        );

        $coreConfig->add(
            'shared=>Drafts/delete',
            '\\ForkBB\\Models\\Draft\\Delete::class',
            'Drafts/save'
        );

        $coreConfig->add(
            'multiple=>Drafts',
            '\\ForkBB\\Models\\Pages\\Drafts::class',
            'Reaction'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.85 to rev.86
     */
    protected function stageNumber85(array $args): ?int
    {
        $config = $this->c->config;

        $config->b_email_use_cron ??= 0;

        $config->save();

        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>MailQueue',
            [
                'class' => '\\ForkBB\\Core\\Mail\\FileQueue::class',
                'path'  => '\'%DIR_CACHE%/mail\'',
            ],
            'Mail'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.86 to rev.87
     */
    protected function stageNumber86(array $args): ?int
    {
        $config = $this->c->config;

        $config->i_censoring_count      ??= 0;
        $config->b_hide_guest_email_fld ??= 0;
        $config->b_regs_disable_email   ??= 0;

        $config->save();

        $this->c->DB->addField('::online', 'o_misc', 'INT(10) UNSIGNED', false, 0);

        // bbcode
        $queryI  = 'INSERT INTO ::bbcode (bb_tag, bb_edit, bb_delete, bb_structure)
            VALUES(?s:tag, 1, 0, ?s:structure)';
        $queryU  = 'UPDATE ::bbcode
            SET bb_edit=1, bb_delete=0, bb_structure=?s:structure
            WHERE bb_tag=?s:tag';
        $bbcodes = include $this->c->DIR_CONFIG . '/defaultBBCode.php';

        $tagsUpd = ['ROOT', 'hide'];
        $tagsUpd = \array_flip($tagsUpd);

        foreach ($bbcodes as $bbcode) {
            if (! isset($tagsUpd[$bbcode['tag']])) {
                continue;
            }

            $vars = [
                ':tag'       => $bbcode['tag'],
                ':structure' => \json_encode($bbcode, FORK_JSON_ENCODE),
            ];
            $exist = $this->c->DB->query('SELECT 1 FROM ::bbcode WHERE bb_tag=?s:tag', $vars)->fetchColumn();
            $query = empty($exist) ? $queryI : $queryU;

            $this->c->DB->exec($query, $vars);
        }
        // bbcode

        return null;
    }
}
