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
use ForkBB\Models\Config\Model as Config;
use function \ForkBB\__;

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
                    'o_maintenance'         => 'required|integer|in:0,1',
                    'o_maintenance_message' => 'string:trim|max:65000 bytes|check_message|html',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $this->c->config->o_maintenance         = $v->o_maintenance;
                $this->c->config->o_maintenance_message = $v->o_maintenance_message;
                $this->c->config->save();

                return $this->c->Redirect->page('AdminMaintenance')->message('Data updated redirect');
            }

            $this->fIswev = $v->getErrors();
        }

        $this->nameTpl         = 'admin/maintenance';
        $this->aIndex          = 'maintenance';
        $this->formMaintenance = $this->formMaintenance($config);
        $this->formRebuild     = $this->formRebuild();

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
                    'legend' => __('Maintenance head'),
                    'fields' => [
                        'o_maintenance' => [
                            'type'    => 'radio',
                            'value'   => $config->o_maintenance,
                            'values'  => [1 => __('Yes'), 0 => __('No')],
                            'caption' => __('Maintenance mode label'),
                            'help'    => 'Maintenance mode help',
                        ],
                        'o_maintenance_message' => [
                            'type'    => 'textarea',
                            'value'   => $config->o_maintenance_message,
                            'caption' => __('Maintenance message label'),
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
        return [
            'action' => $this->c->Router->link('AdminMaintenanceRebuild'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminMaintenanceRebuild'),
            ],
            'sets'   => [
                'indx-info' => [
                    'info' => [
                        [
                            'value' => __('Rebuild index info'),
                            'html'  => true,
                        ],
                    ],
                ],
                'indx' => [
                    'legend' => __('Rebuild index head'),
                    'fields' => [
                        'limit' => [
                            'type'    => 'number',
                            'min'     => '1',
                            'max'     => '9999',
                            'value'   => '100',
                            'caption' => __('Posts per cycle label'),
                            'help'    => 'Posts per cycle help',
                        ],
                        'start' => [
                            'type'    => 'number',
                            'min'     => '1',
                            'max'     => '9999999999',
                            'value'   => '1',
                            'caption' => __('Starting post label'),
                            'help'    => 'Starting post help',
                        ],
                        'clear' => [
                            'type'    => 'checkbox',
                            'value'   => '1',
                            'checked' => true,
                            'caption' => __('Empty index label'),
                            'label'   => __('Empty index help'),
                        ],
                    ],
                ],
                'indx-info2' => [
                    'info' => [
                        [
                            'value' => __('Rebuild completed info'),
                            'html'  => true,
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

    }

    /**
     * Подстановка значения по умолчанию
     */
    public function vCheckMessage(Validator $v, $value)
    {
        if (
            1 === $v->o_maintenance
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

        @\set_time_limit(0);

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

            return $this->c->Redirect->page('AdminRebuildIndex', $args)->message(['Processed posts', $v->start, $last]);
        } else {
            return $this->c->Redirect->page('AdminMaintenance')->message('Rebuilding index end');
        }
    }
}
