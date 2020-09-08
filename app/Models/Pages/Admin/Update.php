<?php

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
    const PHP_MIN = '7.3.0';

    const LATEST_REV_WITH_DB_CHANGES = 9;

    const LOCK_NAME = 'lock_update';
    const LOCk_TTL  = 1800;

    const CONFIG_FILE = 'main.php';

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $container->Lang->load('validator');
        $container->Lang->load('admin_update');

        $this->aIndex     = 'update';
        $this->httpStatus = 503;
        $this->onlinePos  = null;
        $this->nameTpl    = 'admin/form';
        $this->titleForm  = __('Update ForkBB');
        $this->classForm  = 'updateforkbb';

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
     *
     * @return array
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
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
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
                            $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);
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
     *
     * @param Validator $v
     * @param string $dbpass
     *
     * @return string
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
                            'type'  => '', //????
                            'value' => __('Update message'),
#                            'html'  => true,
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
                            'type'  => '', //????
                            'value' => __('Members message'),
//                            'html'  => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'start' => [
                    'type'      => 'submit',
                    'value'     => __('Start update'),
//                    'accesskey' => 's',
                ],
            ],
        ];
    }

    /**
     * Обновляет форум
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function stage(array $args, string $method): Page
    {
        try {
            $uid = $this->setLock($args['uid']);

            if (null === $uid) {
                return $this->returnMaintenance();
            }

            $stage = \max((int) $args['stage'], (int) $this->c->config->i_fork_revision);

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

            $this->c->Cache->clear();

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
#        $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);
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
        $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);

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
        $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);

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
        $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);

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

        $vars  = [
            ':sig_use'    => '1' == $this->c->config->o_signatures ? 1 : 0,
            ':sig_length' => $this->c->config->p_sig_length > 10000 ? 10000 : (int) $this->c->config->p_sig_length,
            ':sig_lines'  => $this->c->config->p_sig_lines> 255 ? 255 : (int) $this->c->config->p_sig_lines,
        ];
        $query = 'UPDATE ::groups
            SET g_sig_use=?i:sig_use, g_sig_length=?i:sig_length, g_sig_lines=?i:sig_lines';

        $this->c->DB->query($query, $vars);

        $vars  = [
            ':grp' => $this->c->GROUP_ADMIN,
        ];
        $query = 'UPDATE ::groups
            SET g_sig_use=1, g_sig_length=10000, g_sig_lines=255
            WHERE g_id=?i:grp';

        $this->c->DB->query($query, $vars);

        $vars  = [
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
        $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);

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
        $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);

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
        $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);

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
        $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);

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
        $coreConfig = new CoreConfig($this->c->DIR_CONFIG . '/' . self::CONFIG_FILE);

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
}
