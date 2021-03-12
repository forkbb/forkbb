<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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

        foreach ($this->c->groups->getList() as $group) {
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
                    $this->fIswev = ['v', 'Bad IP message'];
                    $data         = $v->getData();
                } else {
                    return $this->c->Redirect->page('AdminUsersResult', ['data' => $this->encodeData($ip)]);
                }
            } else {
                $v = $this->c->Validator->reset()
                    ->addValidators([
                    ])->addRules([
                        'token'           => 'token:AdminUsers',
                        'username'        => 'string|max:25',
                        'email'           => 'string|max:80',
                        'title'           => 'string|max:50',
                        'realname'        => 'string|max:40',
                        'gender'          => 'integer|in:0,1,2',
                        'url'             => 'string|max:100',
                        'location'        => 'string|max:30',
                        'signature'       => 'string|max:512',
                        'admin_note'      => 'string|max:30',
                        'num_posts_1'     => 'integer|min:0|max:9999999999',
                        'num_posts_2'     => 'integer|min:0|max:9999999999',
                        'last_post_1'     => 'date',
                        'last_post_2'     => 'date',
                        'last_visit_1'    => 'date',
                        'last_visit_2'    => 'date',
                        'registered_1'    => 'date',
                        'registered_2'    => 'date',
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
                        'order_by'        => 'Order by label',
#                        'direction'       => ,
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

        if ($this->c->userRules->viewIP) {
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
                    'legend' => __('New user'),
                    'fields' => [],
                ]
            ],
            'btns'   => [
                'new' => [
                    'type'  => 'btn',
                    'value' => __('Add'),
                    'link'  => $this->c->Router->link('AdminUsersNew'),
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
            'info' => [
                [
                    'value' => __('User search info'),
                ],
            ],
        ];
        $fields = [];
        $fields['username'] = [
            'type'      => 'text',
            'maxlength' => '25',
            'caption'   => 'Username label',
            'value'     => $data['username'] ?? null,
        ];
        $fields['email'] = [
            'type'      => 'text',
            'maxlength' => '80',
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
            0 => __('Do not display'),
            1 => __('Male'),
            2 => __('Female'),
        ];
        $fields['gender'] = [
#            'class'   => 'block',
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
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['num_posts_1'] = [
            'type'    => 'number',
            'class'   => 'bstart',
            'min'     => '0',
            'max'     => '9999999999',
            'value'   => $data['num_posts_1'] ?? null,
            'caption' => 'Posts label',
        ];
        $fields['num_posts_2'] = [
            'type'    => 'number',
            'class'   => 'bend',
            'min'     => '0',
            'max'     => '9999999999',
            'value'   => $data['num_posts_2'] ?? null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between2'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['last_post_1'] = [
            'class'     => 'bstart',
            'type'      => 'text',
            'maxlength' => '100',
            'value'     => $data['last_post_1'] ?? null,
            'caption'   => 'Last post label',
        ];
        $fields['last_post_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => '100',
            'value'     => $data['last_post_2'] ?? null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between3'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['last_visit_1'] = [
            'class'     => 'bstart',
            'type'      => 'text',
            'maxlength' => '100',
            'value'     => $data['last_visit_1'] ?? null,
            'caption'   => 'Last visit label',
        ];
        $fields['last_visit_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => '100',
            'value'     => $data['last_visit_2'] ?? null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['between4'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['registered_1'] = [
            'class'     => 'bstart',
            'type'      => 'text',
            'maxlength' => '100',
            'value'     => $data['registered_1'] ?? null,
            'caption'   => 'Registered label',
        ];
        $fields['registered_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => '100',
            'value'     => $data['registered_2'] ?? null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $form['sets']['filters'] = [
            'legend' => __('User search subhead'),
            'fields' => $fields,
        ];

        $fields = [];
        $fields['between5'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['order_by'] = [
            'class'   => 'bstart',
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
            'class'   => 'bend',
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
            'legend' => __('Search results legend'),
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
            'legend' => __('IP search subhead'),
            'fields' => $fields,
        ];

        return $form;
    }

    /**
     * Пересчитывает количество сообщений пользователей
     */
    public function recalculate(array $args, string $method): Page
    {
        $v = $this->c->Validator->reset()
        ->addValidators([
        ])->addRules([
            'token' => 'token:AdminUsersRecalculate',
        ])->addAliases([
        ])->addArguments([
        ])->addMessages([
        ]);

        if (! $v->validation($_POST)) {
            return $this->c->Message->message($this->c->Csrf->getError() ?? 'Bad token');
        }

        $this->c->users->updateCountPosts();

        return $this->c->Redirect->page('AdminUsers')->message('Updated the number of users posts redirect');
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
                    'legend' => __('Number of users posts'),
                    'fields' => [],
                ]
            ],
            'btns'   => [
                'recalculate' => [
                    'type'  => 'submit',
                    'value' => __('Recalculate'),
                ],
            ],
        ];

        return $form;
    }
}
