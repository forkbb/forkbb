<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Config\Model as Config;

class Maintenance extends Admin
{
    /**
     * Обслуживание
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('admin_maintenance');

        $config = clone $this->c->config;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_message' => [$this, 'vCheckMessage'],
                ])->addRules([
                    'token'                 => 'token:AdminMaintenance',
                    'o_maintenance'         => 'required|integer|in:0,1',
                    'o_maintenance_message' => 'string:trim|max:65000 bytes|check_message',
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
     *
     * @param Config $config
     *
     * @return array
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
                    'legend' => \ForkBB\__('Maintenance head'),
                    'fields' => [
                        'o_maintenance' => [
                            'type'    => 'radio',
                            'value'   => $config->o_maintenance,
                            'values'  => [1 => \ForkBB\__('Yes'), 0 => \ForkBB\__('No')],
                            'caption' => \ForkBB\__('Maintenance mode label'),
                            'info'    => \ForkBB\__('Maintenance mode help'),
                        ],
                        'o_maintenance_message' => [
                            'type'    => 'textarea',
                            'value'   => $config->o_maintenance_message,
                            'caption' => \ForkBB\__('Maintenance message label'),
                            'info'    => \ForkBB\__('Maintenance message help'),
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Save changes'),
                    'accesskey' => 's',
                ],
            ],
        ];
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @return array
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
                        'info1' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Rebuild index info'),
                            'html'  => true,
                        ],
                    ],
                ],
                'indx' => [
                    'legend' => \ForkBB\__('Rebuild index head'),
                    'fields' => [
                        'limit' => [
                            'type'    => 'number',
                            'min'     => 1,
                            'max'     => 9999,
                            'value'   => 100,
                            'caption' => \ForkBB\__('Posts per cycle label'),
                            'info'    => \ForkBB\__('Posts per cycle help'),
                        ],
                        'start' => [
                            'type'    => 'number',
                            'min'     => 1,
                            'max'     => 9999999999,
                            'value'   => 1,
                            'caption' => \ForkBB\__('Starting post label'),
                            'info'    => \ForkBB\__('Starting post help'),
                        ],
                        'clear' => [
                            'type'    => 'checkbox',
                            'value'   => '1',
                            'checked' => true,
                            'caption' => \ForkBB\__('Empty index label'),
                            'label'   => \ForkBB\__('Empty index help'),
                        ],
                    ],
                ],
                'indx-info2' => [
                    'info' => [
                        'info1' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Rebuild completed info'),
                            'html'  => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'rebuild' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Rebuild index'),
                    'accesskey' => 'r',
                ],
            ],
        ];

    }

    /**
     * Подстановка значения по умолчанию
     *
     * @param Validator $v
     * @param string $value
     *
     * @return string
     */
    public function vCheckMessage(Validator $v, $value)
    {
        if (1 === $v->o_maintenance && 0 === strlen($value)) {
            $value = \ForkBB\__('Default maintenance message');
        }
        return $value;
    }

    /**
     * Пересоздание поискового индекса
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function rebuild(array $args, string $method): Page
    {
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

        if (('POST' === $method && ! $v->validation($_POST))
            || ('POST' !== $method && ! $v->validation($args))
        ) {
            $this->fIswev = $v->getErrors();
            return $this->view([], 'GET');
        }

        @\set_time_limit(0);

        if ('POST' === $method && $v->clear) {
            $this->c->search->truncateIndex();
        }

        $last = $this->c->posts->rebuildIndex($v->start, $v->limit, $v->clear ? 'add' : 'edit');

        if ($last) {
            $args = [
                'token' => '',
                'limit' => $v->limit,
                'start' => $last + 1,
                'clear' => $v->clear ? '1' : '0',
            ];
            $args['token'] = $this->c->Csrf->create('AdminRebuildIndex', $args);

            return $this->c->Redirect->page('AdminRebuildIndex', $args)->message(\ForkBB\__('Processed posts', $v->start, $last));
        } else {
            return $this->c->Redirect->page('AdminMaintenance')->message('Rebuilding index end');
        }
    }
}
