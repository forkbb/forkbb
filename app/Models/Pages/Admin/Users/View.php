<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Users;
use function \ForkBB\__;

class View extends Users
{
    /**
     * Генерирует список доступных групп пользователей
     */
    protected function groups(bool $onlyKeys = false): array
    {
        $groups = [
            -1 => __('All groups'),
            0  => __('Unverified users'),
        ];

        foreach ($this->c->groups->repository as $group) {
            if (! $group->groupGuest) {
                $groups[$group->g_id] = $group->g_title;
            }
        }

        return $onlyKeys ? \array_keys($groups) : $groups;
    }

    /**
     * Подготавливает данные для шаблона поиска пользователей
     */
    public function view(array $args, string $method, array $data = []): Page
    {
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token' => 'token:AdminUsers',
                    'ip'    => 'required',
                ]);

            if ($v->validation($_POST)) {
                $ip = \filter_var($v->ip, \FILTER_VALIDATE_IP);

                if (false === $ip) {
                    $this->fIswev = [FORK_MESS_VLD, 'Bad IP message'];
                    $data         = $v->getData();

                } else {
                    return $this->c->Redirect->page('AdminUsersResult', ['data' => $this->encodeData($ip)]);
                }

            } else {
                $v = $this->c->Validator->reset()
                    ->addValidators([
                    ])->addRules([
                        'token'           => 'token:AdminUsers',
                        'username'        => 'string:null|max:190',
                        'email'           => 'string:null|max:' . $this->c->MAX_EMAIL_LENGTH,
                        'title'           => 'string:null|max:50',
                        'realname'        => 'string:null|max:40',
                        'gender'          => 'integer|in:' . FORK_GEN_NOT . ',' . FORK_GEN_MAN . ',' . FORK_GEN_FEM,
                        'url'             => 'string:null|max:100',
                        'sn_profile1'     => 'string:null|max:200',
                        'sn_profile2'     => 'string:null|max:200',
                        'sn_profile3'     => 'string:null|max:200',
                        'sn_profile4'     => 'string:null|max:200',
                        'sn_profile5'     => 'string:null|max:200',
                        'location'        => 'string:null|max:30',
                        'signature'       => 'string:null|max:512',
                        'admin_note'      => 'string:null|max:30',
                        'num_posts_1'     => 'integer|min:0|max:9999999999',
                        'num_posts_2'     => 'integer|min:0|max:9999999999',
                        'last_post_1'     => 'date',
                        'last_post_2'     => 'date',
                        'last_visit_1'    => 'date',
                        'last_visit_2'    => 'date',
                        'registered_1'    => 'date',
                        'registered_2'    => 'date',
                        'num_drafts_1'    => 'integer|min:0|max:9999999999',
                        'num_drafts_2'    => 'integer|min:0|max:9999999999',
                        'order_by'        => 'required|string|in:username,email,num_posts,last_post,last_visit,registered',
                        'direction'       => 'required|string|in:ASC,DESC',
                        'user_group'      => 'required|integer|in:' . \implode(',', $this->groups(true)),
                    ])->addAliases([
                        'username'        => 'Username label',
                        'email'           => 'E-mail address label',
                        'title'           => 'Title label',
                        'realname'        => 'Real name label',
                        'gender'          => 'Gender label',
                        'url'             => 'Website label',
                        'location'        => 'Location label',
                        'signature'       => 'Signature label',
                        'admin_note'      => 'Admin note label',
                        'num_posts_1'     => 'Posts label',
                        'num_posts_2'     => 'Posts label',
                        'last_post_1'     => 'Last post label',
                        'last_post_2'     => 'Last post label',
                        'last_visit_1'    => 'Last visit label',
                        'last_visit_2'    => 'Last visit label',
                        'registered_1'    => 'Registered label',
                        'registered_2'    => 'Registered label',
                        'num_drafts_1'    => 'Drafts label',
                        'num_drafts_2'    => 'Drafts label',
                        'order_by'        => 'Order by label',
                        'user_group'      => 'User group label',
                    ])->addArguments([
                    ])->addMessages([
                    ]);

                if ($v->validation($_POST)) {
                    return $this->c->Redirect->page('AdminUsersResult', ['data' => $this->encodeData($v->getData())]);
                }

                $this->fIswev = $v->getErrors();
                $data         = $v->getData();
            }
        }

        $this->nameTpl    = 'admin/users';
        $this->formSearch = $this->form($data);

        if ($this->userRules->viewIP) {
            $this->formIP = $this->formIP($data);
        }

        if ($this->user->isAdmin) {
            $this->formNew         = $this->formNew();
            $this->formRecalculate = $this->formRecalculate();
        }

        return $this;
    }

    /**
     * Создает массив данных для кнопки добавления пользователя
     */
    protected function formNew(): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminUsers'),
            'hidden' => [],
            'sets'   => [
                'new' => [
                    'legend' => 'New user',
                    'fields' => [],
                ]
            ],
            'btns'   => [
                'new' => [
                    'type'  => 'btn',
                    'value' => __('Add'),
                    'href'  => $this->c->Router->link('AdminUsersNew'),
                ],
            ],
        ];

        return $form;
    }

    /**
     * Создает массив данных для формы поиска
     */
    protected function form(array $data): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminUsers'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUsers'),
            ],
            'sets'   => [],
            'btns'   => [
                'search' => [
                    'type'  => 'submit',
                    'value' => __('Submit search'),
                ],
            ],
        ];
        $form['sets']['search-info'] = [
            'inform' => [
                [
                    'message' => 'User search info',
                ],
            ],
        ];
        $fields = [];
        $fields['username'] = [
            'type'      => 'text',
            'maxlength' => '190',
            'caption'   => 'Username label',
            'value'     => $data['username'] ?? null,
        ];
        $fields['email'] = [
            'type'      => 'text',
            'maxlength' => (string) $this->c->MAX_EMAIL_LENGTH,
            'caption'   => 'E-mail address label',
            'value'     => $data['email'] ?? null,
        ];
        $fields['title'] = [
            'type'      => 'text',
            'maxlength' => '50',
            'caption'   => 'Title label',
            'value'     => $data['title'] ?? null,
        ];
        $fields['realname'] = [
            'type'      => 'text',
            'maxlength' => '40',
            'caption'   => 'Real name label',
            'value'     => $data['realname'] ?? null,
        ];
        $genders = [
            FORK_GEN_NOT => __('Do not display'),
            FORK_GEN_MAN => __('Male'),
            FORK_GEN_FEM => __('Female'),
        ];
        $fields['gender'] = [
            'type'    => 'radio',
            'value'   => $data['gender'] ?? -1,
            'values'  => $genders,
            'caption' => 'Gender label',
        ];
        $fields['url'] = [
            'id'        => 'website',
            'type'      => 'text',
            'maxlength' => '100',
            'caption'   => 'Website label',
            'value'     => $data['url'] ?? null,
        ];

        for ($i = 1; $i < 6; $i++) {
            $f = "sn_profile{$i}";

            $fields[$f] = [
                'type'      => 'text',
                'maxlength' => '200',
                'caption'   => ['SN profile %s', "{$i}"],
                'value'     => $data[$f] ?? null,
            ];
        }

        $fields['location'] = [
            'type'      => 'text',
            'maxlength' => '30',
            'caption'   => 'Location label',
            'value'     => $data['location'] ?? null,
        ];
        $fields['signature'] = [
            'type'      => 'text',
            'maxlength' => '512',
            'caption'   => 'Signature label',
            'value'     => $data['signature'] ?? null,
        ];
        $fields['admin_note'] = [
            'type'      => 'text',
            'maxlength' => '30',
            'caption'   => 'Admin note label',
            'value'     => $data['admin_note'] ?? null,
        ];
        $fields['between1'] = [
            'class' => ['between'],
            'type'  => 'wrap',
        ];
        $fields['num_posts_1'] = [
            'type'    => 'number',
            'class'   => ['bstart'],
            'min'     => '0',
            'max'     => '9999999999',
            'value'   => $data['num_posts_1'] ?? null,
            'caption' => 'Posts label',
        ];
        $fields['num_posts_2'] = [
            'type'    => 'number',
            'class'   => ['bend'],
            'min'     => '0',
            'max'     => '9999999999',
            'value'   => $data['num_posts_2'] ?? null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between2'] = [
            'class' => ['between'],
            'type'  => 'wrap',
        ];
        $fields['last_post_1'] = [
            'class'     => ['bstart'],
            'type'      => 'datetime-local',
            'value'     => $data['last_post_1'] ?? null,
            'caption'   => 'Last post label',
            'step'      => '1',
        ];
        $fields['last_post_2'] = [
            'class'     => ['bend'],
            'type'      => 'datetime-local',
            'value'     => $data['last_post_2'] ?? null,
            'step'      => '1',
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between3'] = [
            'class' => ['between'],
            'type'  => 'wrap',
        ];
        $fields['last_visit_1'] = [
            'class'     => ['bstart'],
            'type'      => 'datetime-local',
            'value'     => $data['last_visit_1'] ?? null,
            'caption'   => 'Last visit label',
            'step'      => '1',
        ];
        $fields['last_visit_2'] = [
            'class'     => ['bend'],
            'type'      => 'datetime-local',
            'value'     => $data['last_visit_2'] ?? null,
            'step'      => '1',
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between4'] = [
            'class' => ['between'],
            'type'  => 'wrap',
        ];
        $fields['registered_1'] = [
            'class'     => ['bstart'],
            'type'      => 'datetime-local',
            'value'     => $data['registered_1'] ?? null,
            'caption'   => 'Registered label',
            'step'      => '1',
        ];
        $fields['registered_2'] = [
            'class'     => ['bend'],
            'type'      => 'datetime-local',
            'value'     => $data['registered_2'] ?? null,
            'step'      => '1',
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between5'] = [
            'class' => ['between'],
            'type'  => 'wrap',
        ];
        $fields['num_drafts_1'] = [
            'type'    => 'number',
            'class'   => ['bstart'],
            'min'     => '0',
            'max'     => '9999999999',
            'value'   => $data['num_drafts_1'] ?? null,
            'caption' => 'Drafts label',
        ];
        $fields['num_drafts_2'] = [
            'type'    => 'number',
            'class'   => ['bend'],
            'min'     => '0',
            'max'     => '9999999999',
            'value'   => $data['num_drafts_2'] ?? null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $form['sets']['filters'] = [
            'legend' => 'User search subhead',
            'fields' => $fields,
        ];

        $fields = [];
        $fields['between5'] = [
            'class' => ['between'],
            'type'  => 'wrap',
        ];
        $fields['order_by'] = [
            'class'   => ['bstart'],
            'type'    => 'select',
            'options' => [
                'username'   => __('Order by username'),
                'email'      => __('Order by e-mail'),
                'num_posts'  => __('Order by posts'),
                'last_post'  => __('Order by last post'),
                'last_visit' => __('Order by last visit'),
                'registered' => __('Order by registered'),
            ],
            'value'   => $data['order_by'] ?? 'registered',
            'caption' => 'Order by label',
        ];
        $fields['direction'] = [
            'class'   => ['bend'],
            'type'    => 'select',
            'options' => [
                'ASC'  => __('Ascending'),
                'DESC' => __('Descending'),
            ],
            'value'   => $data['direction'] ?? 'DESC',
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['user_group'] = [
            'type'    => 'select',
            'options' => $this->groups(),
            'value'   => $data['user_group'] ?? -1,
            'caption' => 'User group label',
        ];

        $form['sets']['sorting'] = [
            'legend' => 'Search results legend',
            'fields' => $fields,
        ];

        return $form;
    }

    /**
     * Создает массив данных для формы поиска по IP
     */
    protected function formIP(array $data): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminUsers'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUsers'),
            ],
            'sets'   => [],
            'btns'   => [
                'find' => [
                    'type'  => 'submit',
                    'value' => __('Find IP address'),
                ],
            ],
        ];
        $fields = [];
        $fields['ip'] = [
            'type'      => 'text',
            'maxlength' => '49',
            'caption'   => 'IP address label',
            'value'     => $data['ip'] ?? null,
            'required'  => true,
        ];
        $form['sets']['ip'] = [
            'legend' => 'IP search subhead',
            'fields' => $fields,
        ];

        return $form;
    }

    /**
     * Пересчитывает количество сообщений пользователей
     */
    public function recalculate(array $args, string $method): Page
    {
        if (
            1 !== $this->c->config->b_maintenance
            || $this->c->MAINTENANCE_OFF
        ) {
            return $this->c->Message->message('Maintenance only');
        }

        $v = $this->c->Validator->reset()
        ->addValidators([
        ])->addRules([
            'confirm' => 'checkbox',
            'token'   => 'token:AdminUsersRecalculate',
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

        if (\function_exists('\\set_time_limit')) {
            \set_time_limit(0);
        }

        $this->c->users->updateCountPosts();
        $this->c->users->updateCountTopics();

        return $this->c->Redirect->page('AdminUsers')->message('Updated the number of users posts redirect', FORK_MESS_SUCC);
    }

    /**
     * Создает массив данных для формы пересчета количества сообщений
     */
    protected function formRecalculate(): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminUsersRecalculate'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUsersRecalculate'),
            ],
            'sets'   => [
                'recalculate' => [
                    'legend' => 'Number of users posts',
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
                'recalculate' => [
                    'type'  => 'submit',
                    'value' => __('Recalculate'),
                ],
            ],
        ];

        if (
            1 !== $this->c->config->b_maintenance
            || $this->c->MAINTENANCE_OFF
        ) {
            $form['sets']['maintenance-only'] = [
                'inform' => [
                    [
                        'message' => 'Maintenance only',
                    ],
                ],
            ];
            $form['btns']['recalculate']['disabled'] = true;
        }

        return $form;
    }
}
