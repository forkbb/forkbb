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
use ForkBB\Models\Config\Config;
use function \ForkBB\__;
use RuntimeException;

class Maintenance extends Admin
{
    /**
     * Обслуживание
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_maintenance');

        $config = clone $this->c->config;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_message' => [$this, 'vCheckMessage'],
                ])->addRules([
                    'token'                 => 'token:AdminMaintenance',
                    'b_maintenance'         => 'required|integer|in:0,1',
                    'o_maintenance_message' => 'exist|string:trim|max:65000 bytes|check_message|html',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $this->c->config->b_maintenance         = $v->b_maintenance;
                $this->c->config->o_maintenance_message = $v->o_maintenance_message;
                $this->c->config->save();

                return $this->c->Redirect->page('AdminMaintenance')->message('Data updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->nameTpl         = 'admin/maintenance';
        $this->aIndex          = 'maintenance';
        $this->formMaintenance = $this->formMaintenance($config);
        $this->formRebuild     = $this->formRebuild();
        $this->formClearCache  = $this->formClearCache();

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formMaintenance(Config $config): array
    {
        return [
            'action' => $this->c->Router->link('AdminMaintenance'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminMaintenance'),
            ],
            'sets'   => [
                'maint' => [
                    'legend' => 'Maintenance head',
                    'fields' => [
                        'b_maintenance' => [
                            'type'    => 'radio',
                            'value'   => $config->b_maintenance,
                            'values'  => [1 => __('Yes'), 0 => __('No')],
                            'caption' => 'Maintenance mode label',
                            'help'    => 'Maintenance mode help',
                        ],
                        'o_maintenance_message' => [
                            'type'    => 'textarea',
                            'value'   => $config->o_maintenance_message,
                            'caption' => 'Maintenance message label',
                            'help'    => 'Maintenance message help',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formRebuild(): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminMaintenanceRebuild'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminMaintenanceRebuild'),
            ],
            'sets'   => [
                'indx-info' => [
                    'inform' => [
                        [
                            'message' => 'Rebuild index info',
                        ],
                    ],
                ],
                'indx' => [
                    'legend' => 'Rebuilding search index',
                    'fields' => [
                        'limit' => [
                            'type'    => 'number',
                            'min'     => '1',
                            'max'     => '9999',
                            'value'   => '100',
                            'caption' => 'Posts per cycle label',
                            'help'    => 'Posts per cycle help',
                        ],
                        'start' => [
                            'type'    => 'number',
                            'min'     => '1',
                            'max'     => '9999999999',
                            'value'   => '1',
                            'caption' => 'Starting post label',
                            'help'    => 'Starting post help',
                        ],
                        'clear' => [
                            'type'    => 'checkbox',
                            'checked' => true,
                            'caption' => 'Empty index label',
                            'label'   => 'Empty index help',
                        ],
                    ],
                ],
                'indx-info2' => [
                    'inform' => [
                        [
                            'message' => 'Rebuild completed info',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'rebuild' => [
                    'type'  => 'submit',
                    'value' => __('Rebuild index'),
                ],
            ],
        ];

        if (1 !== $this->c->config->b_maintenance) {
            $form['sets']['maintenance-only'] = [
                'inform' => [
                    [
                        'html' => '- - -',
                    ],
                    [
                        'message' => 'Maintenance only',
                    ],
                ],
            ];
            $form['btns']['rebuild']['disabled'] = true;
        }

        return $form;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formClearCache(): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminMaintenanceClear'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminMaintenanceClear'),
            ],
            'sets'   => [
                'clear-cache' => [
                    'legend' => 'Clearing the cache',
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => 'Confirm action',
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'clear' => [
                    'type'  => 'submit',
                    'value' => __('Clear'),
                ],
            ],
        ];

        return $form;
    }

    /**
     * Подстановка значения по умолчанию
     */
    public function vCheckMessage(Validator $v, string $value): string
    {
        if (
            1 === $v->b_maintenance
            && 0 === \strlen($value)
        ) {
            $value = __('Default maintenance message');
        }

        return $value;
    }

    /**
     * Пересоздание поискового индекса
     */
    public function rebuild(array $args, string $method): Page
    {
        if (1 !== $this->c->config->b_maintenance) {
            return $this->c->Message->message('Maintenance only');
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_maintenance');

        $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'token' => 'token:' . ('POST' === $method ? 'AdminMaintenanceRebuild' : 'AdminRebuildIndex'),
                'limit' => 'required|integer|min:1|max:9999',
                'start' => 'required|integer|min:1|max:9999999999',
                'clear' => 'checkbox',
            ])->addAliases([
            ])->addArguments([
                'token' => $args,
            ])->addMessages([
            ]);

        if (
            (
                'POST' === $method
                && ! $v->validation($_POST)
            )
            || (
                'POST' !== $method
                && ! $v->validation($args)
            )
        ) {
            $this->fIswev = $v->getErrors();

            return $this->view([], 'GET');
        }

        if (\function_exists('\\set_time_limit')) {
            \set_time_limit(0);
        }

        if (
            'POST' === $method
            && $v->clear
        ) {
            $this->c->search->truncateIndex();
        }

        $last = $this->c->posts->rebuildIndex($v->start, $v->limit, $v->clear ? 'add' : 'edit');

        if ($last) {
            $args = [
                'token' => null,
                'limit' => $v->limit,
                'start' => $last + 1,
                'clear' => $v->clear ? '1' : '0',
            ];

            return $this->c->Redirect->page('AdminRebuildIndex', $args)->message(['Processed posts', $v->start, $last], FORK_MESS_SUCC);
        } else {
            return $this->c->Redirect->page('AdminMaintenance')->message('Rebuilding index end', FORK_MESS_SUCC);
        }
    }

    /**
     * Пересчитывает количество сообщений пользователей
     */
    public function clearCache(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_maintenance');

        $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'confirm' => 'checkbox',
                'token'   => 'token:AdminMaintenanceClear',
            ])->addAliases([
            ])->addArguments([
            ])->addMessages([
            ]);

        if (
            ! $v->validation($_POST)
            || '1' !== $v->confirm
        ) {
            return $this->c->Message->message(
                '1' !== $v->confirm ? 'No confirm redirect' : ($this->c->Csrf->getError() ?? 'Bad token')
            );
        }

        if (true !== $this->c->Cache->clear()) {
            throw new RuntimeException('Unable to clear cache');
        }

        return $this->c->Redirect->page('AdminMaintenance')->message('Clear cache redirect', FORK_MESS_SUCC);
    }
}
