<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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
    const PHP_MIN                    = '7.3.0';
    const LATEST_REV_WITH_DB_CHANGES = 31;
    const LOCK_NAME                  = 'lock_update';
    const LOCk_TTL                   = 1800;
    const JSON_OPTIONS               = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;
    const CONFIG_FILE                = 'main.php';

    protected $configFile;

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
        $this->classForm  = 'updateforkbb';
        $this->configFile = $container->DIR_APP . '/config/' . self::CONFIG_FILE;

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
        $maintenance->fIswev = ['w', __('Update script is running')];

        if ($isStage) {
            $maintenance->fIswev = ['e', __('Script runs error')];
        }

        return $maintenance;
    }

    /**
     * Проверяет наличие блокировки скрипта обновления
     */
    protected function hasLock(string $uid = null): bool
    {
        $lock = $this->c->Cache->get(self::LOCK_NAME);

        if (null === $uid) {
            return ! empty($lock);
        } else {
            return empty($lock) || ! \hash_equals($uid, (string) $lock);
        }
    }

    protected function setLock(string $uid = null): ?string
    {
        if (true === $this->hasLock($uid)) {
            return null;
        }

        if (null === $uid) {
            $uid = $this->c->Secury->randomHash(33);
        }

        $this->c->Cache->set(self::LOCK_NAME, $uid, self::LOCk_TTL);

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
                    'dbpass'                => 'required|string:trim|check_pass',
                    'o_maintenance_message' => 'required|string:trim|max:65000 bytes',
                ])->addAliases([
                    'dbpass'                => 'Database password',
                    'o_maintenance_message' => 'Maintenance message',
                ])->addMessages([
                ]);

                if ($v->validation($_POST)) {
                    $e = null;

                    // версия PHP
                    if (
                        null === $e
                        && \version_compare(\PHP_VERSION, self::PHP_MIN, '<')
                    ) {
                        $e = __('You are running error', 'PHP', \PHP_VERSION, $this->c->FORK_REVISION, self::PHP_MIN);
                    }

                    // база не от ForkBB ????
                    if (
                        null === $e
                        && $this->c->config->i_fork_revision < 1
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
                        $test_table = 'test_tb_for_update';

                        if (
                            null === $e
                            && true === $this->c->DB->tableExists($test_table)
                        ) {
                            $e = __('The %s table already exists. Delete it.', $test_table);
                        }

                        $schema = [
                            'FIELDS' => [
                                'id' => ['SERIAL', false],
                            ],
                            'PRIMARY KEY' => ['id'],
                        ];
                        if (
                            null === $e
                            && false === $this->c->DB->createTable($test_table, $schema)
                        ) {
                            $e = __('Unable to create %s table', $test_table);
                        }

                        if (
                            null === $e
                            && false === $this->c->DB->addField($test_table, 'test_field', 'VARCHAR(80)', false, '')
                        ) {
                            $e = __('Unable to add test_field field to %s table', $test_table);
                        }

                        $sql = "INSERT INTO ::{$test_table} (test_field) VALUES ('TEST_VALUE')";
                        if (
                            null === $e
                            && false === $this->c->DB->exec($sql)
                        ) {
                            $e = __('Unable to insert line to %s table', $test_table);
                        }

                        if (
                            null === $e
                            && false === $this->c->DB->dropField($test_table, 'test_field')
                        ) {
                            $e = __('Unable to drop test_field field from %s table', $test_table);
                        }

                        if (
                            null === $e
                            && false === $this->c->DB->dropTable($test_table)
                        ) {
                            $e = __('Unable to drop %s table', $test_table);
                        }
                    }

                    if (\is_string($e)) {
                        return $this->c->Message->message($e, true, 503);
                    }

                    $uid = $this->setLock();

                    if (null === $uid) {
                        $this->fIswev = ['e', __('Unable to write update lock')];
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
    public function vCheckPass(Validator $v, $dbpass)
    {
        if (\substr($this->c->DB_DSN, 0, 6) === 'sqlite') {
            if (! \hash_equals($this->c->DB_DSN, "sqlite:{$dbpass}")) {  // ????
                $v->addError(__('Invalid file error', self::CONFIG_FILE));
            }
        } else {
            if (! \hash_equals($this->c->DB_PASSWORD, $dbpass)) {
                $v->addError(__('Invalid password error', self::CONFIG_FILE));
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
                    'info' => [
                        'info1' => [
                            'value' => __('Update message'),
                        ],
                    ],
                ],
                'update' => [
                    'legend' => __('Update ForkBB'),
                    'fields' => [
                        'dbpass' => [
                            'type'     => 'password',
                            'value'    => '',
                            'caption'  => __('Database password'),
                            'info'     => __('Database password note'),
                            'required' => true,
                        ],
                        'o_maintenance_message' => [
                            'type'     => 'textarea',
                            'value'    => $v ? $v->o_maintenance_message : $this->c->config->o_maintenance_message,
                            'caption'  => __('Maintenance message'),
                            'info'     => __('Maintenance message info'),
                            'required' => true,
                        ],
                    ],
                ],
                'member-info' => [
                    'info' => [
                        'info1' => [
                            'value' => __('Members message'),
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
                    $start = $this->{'stageNumber' . $stage}($args);

                    if (null === $start) {
                        ++$stage;
                    }

                    return $this->c->Redirect->page(
                        'AdminUpdateStage',
                        ['uid' => $uid, 'stage' => $stage, 'start' => $start]
                    )->message(__('Stage %1$s (%2$s)', $stage, (int) $start));
                }

                ++$stage;
            } while ($stage < $this->c->FORK_REVISION);

            $this->c->config->i_fork_revision = $this->c->FORK_REVISION;

            $this->c->config->save();

            if (true !== $this->c->Cache->clear()) {
                throw new RuntimeException('Unable to clear cache');
            }

            return $this->c->Redirect->page('Index')->message('Successfully updated');
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
     * rev.1 to rev.2
     */
    protected function stageNumber1(array $args): ?int
    {
        $this->c->DB->alterField('users', 'gender', 'TINYINT UNSIGNED', false, 0);
        $this->c->DB->alterField('users', 'disp_topics', 'TINYINT UNSIGNED', false, 0);
        $this->c->DB->alterField('users', 'disp_posts', 'TINYINT UNSIGNED', false, 0);

        $this->c->DB->addField('users', 'ip_check_type', 'TINYINT UNSIGNED', false, 0);
        $this->c->DB->addField('users', 'login_ip_cache', 'VARCHAR(255)', false, '');

        return null;
    }

    /**
     * rev.2 to rev.3
     */
    protected function stageNumber2(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>AdminUsersRecalculate',
            '\\ForkBB\\Models\\Pages\\Admin\\Users\\Recalculate::class',
            'AdminUsersNew'
        );
        $coreConfig->add(
            'EOL',
            '\\PHP_EOL'
        );
        $coreConfig->save();

        return null;
    }

    /**
     * rev.3 to rev.4
     */
    protected function stageNumber3(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $result = $coreConfig->delete(
            'multiple=>AdminUsersRecalculate',
        );

        $coreConfig->add(
            'multiple=>UserManagerUpdateLoginIpCache',
            '\\ForkBB\\Models\\User\\UpdateLoginIpCache::class',
            'UserManagerUpdateCountTopics'
        );
        $coreConfig->save();

        return null;
    }

    /**
     * rev.4 to rev.5
     */
    protected function stageNumber4(array $args): ?int
    {
        unset($this->c->config->o_date_format);
        unset($this->c->config->o_time_format);

        $this->c->config->save();

        $query = 'UPDATE ::users
            SET time_format=time_format+1
            WHERE time_format>0';

        $this->c->DB->exec($query);

        $query = 'UPDATE ::users
            SET date_format=date_format+1
            WHERE date_format>0';

        $this->c->DB->exec($query);

        return null;
    }

    /**
     * rev.5 to rev.6
     */
    protected function stageNumber5(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>Email',
            '\\ForkBB\\Models\\Pages\\Email::class',
            'Report'
        );
        $coreConfig->save();

        return null;
    }

    /**
     * rev.6 to rev.7
     */
    protected function stageNumber6(array $args): ?int
    {
        $this->c->DB->addField('groups', 'g_sig_use', 'TINYINT(1)', false, 1);
        $this->c->DB->addField('groups', 'g_sig_length', 'SMALLINT UNSIGNED', false, 400);
        $this->c->DB->addField('groups', 'g_sig_lines', 'TINYINT UNSIGNED', false, 4);

        $vars = [
            ':sig_use'    => '1' == $this->c->config->o_signatures ? 1 : 0,
            ':sig_length' => $this->c->config->p_sig_length > 10000 ? 10000 : (int) $this->c->config->p_sig_length,
            ':sig_lines'  => $this->c->config->p_sig_lines> 255 ? 255 : (int) $this->c->config->p_sig_lines,
        ];
        $query = 'UPDATE ::groups
            SET g_sig_use=?i:sig_use, g_sig_length=?i:sig_length, g_sig_lines=?i:sig_lines';

        $this->c->DB->query($query, $vars);

        $vars = [
            ':grp' => $this->c->GROUP_ADMIN,
        ];
        $query = 'UPDATE ::groups
            SET g_sig_use=1, g_sig_length=10000, g_sig_lines=255
            WHERE g_id=?i:grp';

        $this->c->DB->query($query, $vars);

        $vars = [
            ':grp' => $this->c->GROUP_GUEST,
        ];
        $query = 'UPDATE ::groups
            SET g_sig_use=0, g_sig_length=0, g_sig_lines=0
            WHERE g_id=?i:grp';

        $this->c->DB->query($query, $vars);

        unset($this->c->config->o_signatures);
        unset($this->c->config->p_sig_length);
        unset($this->c->config->p_sig_lines);

        $this->c->config->save();

        return null;
    }

    /**
     * rev.7 to rev.8
     */
    protected function stageNumber7(array $args): ?int
    {
        $this->c->DB->dropField('groups', 'g_sig_use');

        return null;
    }

    /**
     * rev.8 to rev.9
     */
    protected function stageNumber8(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>Feed',
            '\\ForkBB\\Models\\Pages\\Feed::class',
            'Email'
        );
        $coreConfig->add(
            'multiple=>PostManagerFeed',
            '\\ForkBB\\Models\\Post\\Feed::class',
            'PostManagerMove'
        );
        $coreConfig->save();

        return null;
    }

    /**
     * rev.9 to rev.10
     */
    protected function stageNumber9(array $args): ?int
    {
        $this->c->config->i_email_max_recipients = 1;

        $this->c->config->save();

        return null;
    }

    /**
     * rev.10 to rev.11
     */
    protected function stageNumber10(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>subscriptions',
            '\\ForkBB\\Models\\Subscription\\Model::class',
            'search'
        );
        $coreConfig->save();

        return null;
    }

    /**
     * rev.11 to rev.12
     */
    protected function stageNumber11(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>SearchModelActionF',
            '\\ForkBB\\Models\\Search\\ActionF::class',
            'SearchModelActionT'
        );
        $coreConfig->save();

        return null;
    }

    /**
     * rev.12 to rev.13
     */
    protected function stageNumber12(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>SubscriptionModelSend',
            '\\ForkBB\\Models\\Subscription\\Send::class'
        );

        $result = $coreConfig->delete(
            'multiple=>BanListModelIsBanned',
        );
        $coreConfig->add(
            'shared=>BanListModelIsBanned',
            '\\ForkBB\\Models\\BanList\\IsBanned::class'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.13 to rev.14
     */
    protected function stageNumber13(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $result = $coreConfig->delete(
            'multiple=>AdminPermissions',
        );

        $coreConfig->add(
            'multiple=>AdminParser',
            '\\ForkBB\\Models\\Pages\\Admin\\Parser\\Edit::class',
            'AdminReports'
        );

        $coreConfig->add(
            'multiple=>AdminParserSmilies',
            '\\ForkBB\\Models\\Pages\\Admin\\Parser\\Smilies::class',
            'AdminParser'
        );

        $coreConfig->add(
            'multiple=>AdminParserBBCode',
            '\\ForkBB\\Models\\Pages\\Admin\\Parser\\BBCode::class',
            'AdminParserSmilies'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.14 to rev.15
     */
    protected function stageNumber14(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $result = $coreConfig->delete(
            'multiple=>SmileyListModelLoad',
        );
        $coreConfig->add(
            'shared=>SmileyListModelLoad',
            '\\ForkBB\\Models\\SmileyList\\Load::class'
        );

        $coreConfig->add(
            'shared=>SmileyListModelUpdate',
            '\\ForkBB\\Models\\SmileyList\\Update::class'
        );

        $coreConfig->add(
            'shared=>SmileyListModelInsert',
            '\\ForkBB\\Models\\SmileyList\\Insert::class'
        );

        $coreConfig->add(
            'shared=>SmileyListModelDelete',
            '\\ForkBB\\Models\\SmileyList\\Delete::class'
        );

        $coreConfig->save();

        $this->c->DB->renameField('smilies', 'image', 'sm_image');
        $this->c->DB->renameField('smilies', 'text', 'sm_code');
        $this->c->DB->renameField('smilies', 'disp_position', 'sm_position');

        $this->c->DB->alterField('smilies', 'sm_position', 'INT(10) UNSIGNED', false, 0);

        return null;
    }

    /**
     * rev.15 to rev.16
     */
    protected function stageNumber15(array $args): ?int
    {
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
        ];
        $this->c->DB->createTable('bbcode', $schema);

        $query = 'INSERT INTO ::bbcode (bb_tag, bb_edit, bb_delete, bb_structure)
            VALUES(?s:tag, 1, 0, ?s:structure)';

        $bbcodes = include $this->c->DIR_APP . '/config/defaultBBCode.php';
        foreach ($bbcodes as $bbcode) {
            $vars = [
                ':tag'       => $bbcode['tag'],
                ':structure' => \json_encode($bbcode, self::JSON_OPTIONS),
            ];
            $this->c->DB->exec($query, $vars);
        }

        $this->c->config->a_bb_white_mes = [];
        $this->c->config->a_bb_white_sig = ['b', 'i', 'u', 'color', 'colour', 'email', 'url'];
        $this->c->config->a_bb_black_mes = [];
        $this->c->config->a_bb_black_sig = [];

        unset($this->c->config->o_quote_depth);
        unset($this->c->config->p_sig_img_tag);
        unset($this->c->config->p_message_img_tag);

        $this->c->config->save();

        $coreConfig = new CoreConfig($this->configFile);

        $result = $coreConfig->delete(
            'BBCODE_INFO=>forSign',
        );

        $coreConfig->add(
            'shared=>bbcode',
            '\'@BBCodeListModel:init\'',
            'subscriptions'
        );

        $coreConfig->add(
            'shared=>BBCodeListModel',
            [
                'class' => '\\ForkBB\\Models\\BBCodeList\\Model::class',
                'file'  => '\'defaultBBCode.php\'',
            ]
        );

        $coreConfig->add(
            'shared=>BBCodeListModelGenerate',
            '\\ForkBB\\Models\\BBCodeList\\Generate::class'
        );

        $coreConfig->add(
            'shared=>BBCodeListModelLoad',
            '\\ForkBB\\Models\\BBCodeList\\Load::class'
        );

        $coreConfig->add(
            'shared=>BBCodeListModelUpdate',
            '\\ForkBB\\Models\\BBCodeList\\Update::class'
        );

        $coreConfig->add(
            'shared=>BBCodeListModelInsert',
            '\\ForkBB\\Models\\BBCodeList\\Insert::class'
        );

        $coreConfig->add(
            'shared=>BBCodeListModelDelete',
            '\\ForkBB\\Models\\BBCodeList\\Delete::class'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.16 to rev.17
     */
    protected function stageNumber16(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>Router=>csrf',
            '\'@Csrf\''
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.17 to rev.18
     */
    protected function stageNumber17(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>BBStructure',
            '\\ForkBB\\Models\\BBCodeList\\Structure::class'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.18 to rev.19
     */
    protected function stageNumber18(array $args): ?int
    {
        $this->c->DB->addField('users', 'avatar', 'VARCHAR(30)', false, '', 'title');

        $dir     = $this->c->DIR_PUBLIC . $this->c->config->o_avatars_dir . '/';
        $avatars = [];

        if (
            \is_dir($dir)
            && false !== ($dh = \opendir($dir))
        ) {
            while (false !== ($entry = \readdir($dh))) {
                if (
                    \preg_match('%^([1-9]\d*)\.(jpg|gif|png)$%D', $entry, $matches)
                    && \is_file($dir . $entry)
                ) {
                    $avatars[$matches[2]][] = (int) $matches[1];
                }
            }
            \closedir($dh);
        }

        $query = 'UPDATE ::users
            SET avatar=CONCAT(id, \'.\', ?s:ext)
            WHERE id IN (?ai:ids)';

        foreach ($avatars as $ext => $ids) {
            $vars = [
                ':ext' => $ext,
                ':ids' => $ids,
            ];

            $this->c->DB->exec($query, $vars);
        }

        return null;
    }

    /**
     * rev.19 to rev.20
     */
    protected function stageNumber19(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $result = $coreConfig->delete(
            'shared=>FileCache',
        );

        $coreConfig->add(
            'shared=>Cache',
            $result
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.20 to rev.21
     */
    protected function stageNumber20(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>Test',
            '\\ForkBB\\Core\\Test::class',
            'Func'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.21 to rev.22
     */
    protected function stageNumber21(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'USERNAME_PATTERN',
            '\'%^(?=.{2,25}$)\\p{L}[\\p{L}\\p{N}\\x20\\._-]+$%uD\'',
            'FLOOD_INTERVAL'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.22 to rev.23
     */
    protected function stageNumber22(array $args): ?int
    {
        $this->c->config->i_topic_review          = $this->c->config->o_topic_review          ?? 15;
        $this->c->config->i_disp_topics_default   = $this->c->config->o_disp_topics_default   ?? 30;
        $this->c->config->i_disp_posts_default    = $this->c->config->o_disp_posts_default    ?? 25;
        $this->c->config->i_disp_users            = $this->c->config->o_disp_users            ?? 50;
        $this->c->config->i_default_email_setting = $this->c->config->o_default_email_setting ?? 2;
        $this->c->config->i_avatars_width         = $this->c->config->o_avatars_width         ?? 60;
        $this->c->config->i_avatars_height        = $this->c->config->o_avatars_height        ?? 60;
        $this->c->config->i_avatars_size          = $this->c->config->o_avatars_size          ?? 10240;
        $this->c->config->i_feed_type             = $this->c->config->o_feed_type             ?? 2;
        $this->c->config->i_feed_ttl              = $this->c->config->o_feed_ttl              ?? 0;
        $this->c->config->i_report_method         = $this->c->config->o_report_method         ?? 0;
        $this->c->config->i_default_user_group    = $this->c->config->o_default_user_group    ?? $this->c->GROUP_MEMBER;
        $this->c->config->a_max_users = [
            'number' => (int) ($this->c->config->st_max_users ?? 1),
            'time'   => (int) ($this->c->config->st_max_users_time ?? \time()),
        ];

        unset($this->c->config->o_enable_acaptcha);
        unset($this->c->config->o_crypto_salt);
        unset($this->c->config->o_crypto_pas);
        unset($this->c->config->o_crypto_enable);
        unset($this->c->config->o_check_ip);
        unset($this->c->config->o_coding_forms);
        unset($this->c->config->o_fbox_files);
        unset($this->c->config->o_fbox_guest);
        unset($this->c->config->o_show_version);
        unset($this->c->config->o_topic_review);
        unset($this->c->config->o_disp_topics_default);
        unset($this->c->config->o_disp_posts_default);
        unset($this->c->config->o_disp_users);
        unset($this->c->config->o_default_email_setting);
        unset($this->c->config->o_avatars_width);
        unset($this->c->config->o_avatars_height);
        unset($this->c->config->o_avatars_size);
        unset($this->c->config->o_feed_type);
        unset($this->c->config->o_feed_ttl);
        unset($this->c->config->o_report_method);
        unset($this->c->config->o_board_redirect);
        unset($this->c->config->o_board_redirectg);
        unset($this->c->config->o_default_user_group);
        unset($this->c->config->st_max_users);
        unset($this->c->config->st_max_users_time);

        $this->c->config->save();

        return null;
    }

    /**
     * rev.23 to rev.24
     */
    protected function stageNumber23(array $args): ?int
    {
        $this->c->DB->addField('forums', 'last_poster_id', 'INT(10) UNSIGNED', false, 0, 'last_poster');

        $query = 'UPDATE ::forums AS f
            SET f.last_poster_id=COALESCE((
                SELECT u.id
                FROM ::users AS u
                WHERE u.username=f.last_poster AND u.id>1
            ), 0)';
        $this->c->DB->exec($query);

        $this->c->DB->renameField('posts', 'edited_by', 'editor');
        $this->c->DB->addField('posts', 'editor_id', 'INT(10) UNSIGNED', false, 0, 'editor');

        $query = 'UPDATE ::posts AS p
            SET p.editor_id=COALESCE((
                SELECT u.id
                FROM ::users AS u
                WHERE u.username=p.editor AND u.id>1
            ), 0)';
        $this->c->DB->exec($query);

        unset($this->c->config->o_merge_timeout);

        $this->c->config->save();

        return null;
    }

    /**
     * rev.24 to rev.25
     */
    protected function stageNumber24(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>ForumManagerUpdateUsername',
            '\\ForkBB\\Models\\Forum\\UpdateUsername::class',
            'ForumManagerMarkread'
        );

        $coreConfig->add(
            'multiple=>PostManagerUpdateUsername',
            '\\ForkBB\\Models\\Post\\UpdateUsername::class',
            'PostManagerFeed'
        );

        $coreConfig->add(
            'multiple=>TopicManagerUpdateUsername',
            '\\ForkBB\\Models\\Topic\\UpdateUsername::class',
            'TopicManagerMove'
        );

        $coreConfig->add(
            'multiple=>OnlineModelUpdateUsername',
            '\\ForkBB\\Models\\Online\\UpdateUsername::class',
            'OnlineModelInfo'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.25 to rev.26
     */
    protected function stageNumber25(array $args): ?int
    {
        $this->c->DB->renameField('topics', 'poll_kol', 'poll_votes');
        $this->c->DB->renameField('poll', 'question', 'question_id');
        $this->c->DB->renameField('poll', 'field', 'field_id');
        $this->c->DB->renameField('poll', 'choice', 'qna_text');

        $this->c->config->b_poll_enabled       = $this->c->config->o_poll_enabled ?? 0;
        $this->c->config->i_poll_max_questions = $this->c->config->o_poll_max_ques ?? 3;
        $this->c->config->i_poll_max_fields    = $this->c->config->o_poll_max_field ?? 20;
        $this->c->config->i_poll_time          = $this->c->config->o_poll_time ?? 60;
        $this->c->config->i_poll_term          = $this->c->config->o_poll_term ?? 3;
        $this->c->config->b_poll_guest         = $this->c->config->o_poll_guest ?? 0;

        unset($this->c->config->o_poll_enabled);
        unset($this->c->config->o_poll_max_ques);
        unset($this->c->config->o_poll_max_field);
        unset($this->c->config->o_poll_time);
        unset($this->c->config->o_poll_term);
        unset($this->c->config->o_poll_guest);

        $this->c->config->save();

        return null;
    }

    /**
     * rev.26 to rev.27
     */
    protected function stageNumber26(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>polls',
            '\\ForkBB\\Models\\Poll\\Manager::class',
            'posts'
        );

        $coreConfig->add(
            'shared=>PollManagerLoad',
            '\\ForkBB\\Models\\Poll\\Load::class',
            'UsersRules'
        );

        $coreConfig->add(
            'shared=>PollManagerSave',
            '\\ForkBB\\Models\\Poll\\Save::class',
            'PollManagerLoad'
        );

        $coreConfig->add(
            'shared=>PollManagerDelete',
            '\\ForkBB\\Models\\Poll\\Delete::class',
            'PollManagerSave'
        );

        $coreConfig->add(
            'shared=>PollManagerRevision',
            '\\ForkBB\\Models\\Poll\\Revision::class',
            'PollManagerDelete'
        );

        $coreConfig->add(
            'multiple=>PollModel',
            '\\ForkBB\\Models\\Poll\\Model::class',
            'PostManagerUpdateUsername'
        );

        $coreConfig->save();

        return null;
    }

    /**
     * rev.27 to rev.28
     */
    protected function stageNumber27(array $args): ?int
    {
        $this->c->DB->alterField('topics', 'poll_type', 'SMALLINT UNSIGNED', false, 0);

        return null;
    }

    /**
     * rev.28 to rev.29
     */
    protected function stageNumber28(array $args): ?int
    {
        $query = 'UPDATE ::poll AS pl
            SET pl.qna_text=CONCAT(pl.votes, \'|\', pl.qna_text)
            WHERE pl.field_id=0';

        $this->c->DB->query($query);

        $query = 'UPDATE ::poll AS pl, ::topics AS t
            SET pl.votes=t.poll_votes
            WHERE pl.field_id=0 AND pl.tid=t.id';

        $this->c->DB->query($query);

        $this->c->DB->dropField('topics', 'poll_votes');

        return null;
    }

    /**
     * rev.29 to rev.30
     */
    protected function stageNumber29(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'multiple=>Poll',
            '\\ForkBB\\Models\\Pages\\Poll::class',
            'Feed'
        );

        $coreConfig->save();

        return null;
   }

    /**
     * rev.30 to rev.31
     */
    protected function stageNumber30(array $args): ?int
    {
        $queries = [
            'UPDATE ::bbcode SET bb_structure = REPLACE(bb_structure, \'"text only"\', \'"text_only"\')',
            'UPDATE ::bbcode SET bb_structure = REPLACE(bb_structure, \'"no attr"\', \'"No_attr"\')',
            'UPDATE ::bbcode SET bb_structure = REPLACE(bb_structure, \'"self nesting"\', \'"self_nesting"\')',
            'UPDATE ::bbcode SET bb_structure = REPLACE(bb_structure, \'"body format"\', \'"body_format"\')',
            'UPDATE ::bbcode SET bb_structure = REPLACE(bb_structure, \'"tags only"\', \'"tags_only"\')',
            'UPDATE ::bbcode SET bb_structure = REPLACE(bb_structure, \'"text handler"\', \'"text_handler"\')',
        ];

        foreach ($queries as $query) {
            $this->c->DB->exec($query);
        }

        return null;
   }

    /**
     * rev.31 to rev.32
     */
    protected function stageNumber31(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'HTTP_HEADERS',
            [
                'common' => [
                    'X-Content-Type-Options'  => '\'nosniff\'',
                    'X-Frame-Options'         => '\'DENY\'',
                    'X-XSS-Protection'        => '\'1; mode=block\'',
                    'Referrer-Policy'         => '\'origin-when-cross-origin\'',
                    'Content-Security-Policy' => '\'default-src \\\'self\\\';img-src *;object-src \\\'none\\\';frame-ancestors \\\'none\\\'\';base-uri \\\'self\\\';form-action \\\'self\\\'',
                    'Feature-Policy'          => '\'accelerometer \\\'none\\\';ambient-light-sensor \\\'none\\\';autoplay \\\'none\\\';battery \\\'none\\\';camera \\\'none\\\';document-domain \\\'self\\\';fullscreen \\\'self\\\';geolocation \\\'none\\\';gyroscope \\\'none\\\';magnetometer \\\'none\\\';microphone \\\'none\\\';midi \\\'none\\\';payment \\\'none\\\';picture-in-picture \\\'none\\\';sync-xhr \\\'self\\\';usb \\\'none\\\'\'',
                ],
                'secure' => [
                    'X-Content-Type-Options'  => '\'nosniff\'',
                    'X-Frame-Options'         => '\'DENY\'',
                    'X-XSS-Protection'        => '\'1; mode=block\'',
                    'Referrer-Policy'         => '\'origin-when-cross-origin\'',
                    'Content-Security-Policy' => '\'default-src \\\'self\\\';object-src \\\'none\\\';frame-ancestors \\\'none\\\'\';base-uri \\\'self\\\';form-action \\\'self\\\'',
                    'Feature-Policy'          => '\'accelerometer \\\'none\\\';ambient-light-sensor \\\'none\\\';autoplay \\\'none\\\';battery \\\'none\\\';camera \\\'none\\\';document-domain \\\'self\\\';fullscreen \\\'self\\\';geolocation \\\'none\\\';gyroscope \\\'none\\\';magnetometer \\\'none\\\';microphone \\\'none\\\';midi \\\'none\\\';payment \\\'none\\\';picture-in-picture \\\'none\\\';sync-xhr \\\'self\\\';usb \\\'none\\\'\'',
                ],
            ],
            'USERNAME_PATTERN'
        );

        $coreConfig->save();

        return null;
   }

    /**
     * rev.32 to rev.33
     */
    protected function stageNumber32(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>Log',
            [
                'class'  => '\\ForkBB\\Core\\Log::class',
                'config' => [
                    'path'       => '\'%DIR_LOG%/{Y-m-d}.log\'',
                    'lineFormat' => '"\\\\%datetime\\\\% [\\\\%level_name\\\\%] \\\\%message\\\\%\\t\\\\%context\\\\%\\n"',
                    'timeFormat' => '\'Y-m-d H:i:s\'',
                ],
            ],
            'NormEmail'
        );

        $coreConfig->save();

        return null;
   }

    /**
     * rev.33 to rev.34
     */
    protected function stageNumber33(array $args): ?int
    {
        $coreConfig = new CoreConfig($this->configFile);

        $coreConfig->add(
            'shared=>LogViewer',
            [
                'class'  => '\\ForkBB\\Core\\LogViewer::class',
                'config' => [
                    'dir'        => '\'%DIR_LOG%\'',
                    'pattern'    => '\'*.log\'',
                    'lineFormat' => '"\\\\%datetime\\\\% [\\\\%level_name\\\\%] \\\\%message\\\\%\\t\\\\%context\\\\%\\n"',
                ],
                'cache' => '\'%Cache%\'',
            ],
            'Log'
        );

        $coreConfig->add(
            'multiple=>AdminLogs',
            '\\ForkBB\\Models\\Pages\\Admin\\Logs::class',
            'AdminParserBBCode'
        );

        $coreConfig->save();

        return null;
   }
}
