<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use PDO;
use PDOException;
use RuntimeException;
use function \ForkBB\__;

class Update extends Admin
{
    const PHP_MIN = '7.3.0';

    const LOCK_NAME = 'lock_update';
    const LOCk_TTL  = 1800;

    const CONFIG_FILE = 'config/main.php';

    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

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
     * Содержимое файла main.php
     * @var string
     */
    protected $configFile;

    /**
     * Начальная позиция массива конфига в файле main.php
     */
    protected $configArrPos;

    protected function loadAndCheckConfig(): bool
    {
        $this->configFile = \file_get_contents($this->c->DIR_CONFIG . '/main.php');

        if (\preg_match('%\[\s+\'BASE_URL\'\s+=>%', $this->configFile, $matches, \PREG_OFFSET_CAPTURE)) {
            $this->configArrPos = $matches[0][1];

            return true;
        }

        return false;
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
                    // версия PHP
                    if (\version_compare(\PHP_VERSION, self::PHP_MIN, '<')) {
                        return $this->c->Message->message(
                            __('You are running error', 'PHP', \PHP_VERSION, $this->c->FORK_REVISION, self::PHP_MIN),
                            true,
                            503
                        );
                    }

                    // база не от ForkBB ????
                    if ($this->c->config->i_fork_revision < 1) {
                        return $this->c->Message->message(
                            'Version mismatch error',
                            true,
                            503
                        );
                    }

                    // загрузка и проверка конфига
                    if (true !== $this->loadAndCheckConfig()) {
                        return $this->c->Message->message(
                            'The structure of the main.php file is undefined',
                            true,
                            503
                        );
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
    }

#    /**
#     * Выполняет определенный шаг обновления
#     *
#     * Возвращает null, если шаг выпонен
#     * Возвращает положительный int, если требуется продолжить выполнение шага
#     */
#    protected function stageNumber1(array $args): ?int
#    {
#        $this->configAdd(
#            [
#                'multiple' => [
#                    'AdminUsersRecalculate' => '\ForkBB\Models\Pages\Admin\Users\Recalculate::class'
#                ],
#            ],
#            'after:AdminUsersNew'
#        );
#
#        return null;
#    }
}
