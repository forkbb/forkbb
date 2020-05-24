<?php

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Users;

class View extends Users
{
    /**
     * Генерирует список доступных групп пользователей
     *
     * @param bool $onlyKeys
     *
     * @return array
     */
    protected function groups(bool $onlyKeys = false): array
    {
        $groups = [
            -1 => \ForkBB\__('All groups'),
            0  => \ForkBB\__('Unverified users'),
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
     *
     * @param array $args
     * @param string $method
     * @param array $data
     *
     * @return Page
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
                    $this->fIswev = ['v', \ForkBB\__('Bad IP message')];
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

        return $this;
    }

    /**
     * Создает массив данных для формы поиска
     *
     * @param array $data
     *
     * @return array
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
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Submit search'),
                    'accesskey' => 's',
                ],
            ],
        ];
        $form['sets']['search-info'] = [
            'info' => [
                'info1' => [
                    'type'  => '', //????
                    'value' => \ForkBB\__('User search info'),
                ],
            ],
        ];
        $fields = [];
        $fields['username'] = [
            'type'      => 'text',
            'maxlength' => 25,
            'caption'   => \ForkBB\__('Username label'),
            'value'     => isset($data['username']) ? $data['username'] : null,
        ];
        $fields['email'] = [
            'type'      => 'text',
            'maxlength' => 80,
            'caption'   => \ForkBB\__('E-mail address label'),
            'value'     => isset($data['email']) ? $data['email'] : null,
        ];
        $fields['title'] = [
            'type'      => 'text',
            'maxlength' => 50,
            'caption'   => \ForkBB\__('Title label'),
            'value'     => isset($data['title']) ? $data['title'] : null,
        ];
        $fields['realname'] = [
            'type'      => 'text',
            'maxlength' => 40,
            'caption'   => \ForkBB\__('Real name label'),
            'value'     => isset($data['realname']) ? $data['realname'] : null,
        ];
        $genders = [
            0 => \ForkBB\__('Do not display'),
            1 => \ForkBB\__('Male'),
            2 => \ForkBB\__('Female'),
        ];
        $fields['gender'] = [
#            'class'   => 'block',
            'type'    => 'radio',
            'value'   => isset($data['gender']) ? $data['gender'] : -1,
            'values'  => $genders,
            'caption' => \ForkBB\__('Gender label'),
        ];
        $fields['url'] = [
            'id'        => 'website',
            'type'      => 'text',
            'maxlength' => 100,
            'caption'   => \ForkBB\__('Website label'),
            'value'     => isset($data['url']) ? $data['url'] : null,
        ];
        $fields['location'] = [
            'type'      => 'text',
            'maxlength' => 30,
            'caption'   => \ForkBB\__('Location label'),
            'value'     => isset($data['location']) ? $data['location'] : null,
        ];
        $fields['signature'] = [
            'type'      => 'text',
            'maxlength' => 512,
            'caption'   => \ForkBB\__('Signature label'),
            'value'     => isset($data['signature']) ? $data['signature'] : null,
        ];
        $fields['admin_note'] = [
            'type'      => 'text',
            'maxlength' => 30,
            'caption'   => \ForkBB\__('Admin note label'),
            'value'     => isset($data['admin_note']) ? $data['admin_note'] : null,
        ];
        $fields['between1'] = [
            'class' => 'between',
            'type'  => 'wrap',
        ];
        $fields['num_posts_1'] = [
            'type'    => 'number',
            'class'   => 'bstart',
            'min'     => 0,
            'max'     => 9999999999,
            'value'   => isset($data['num_posts_1']) ? $data['num_posts_1'] : null,
            'caption' => \ForkBB\__('Posts label'),
        ];
        $fields['num_posts_2'] = [
            'type'    => 'number',
            'class'   => 'bend',
            'min'     => 0,
            'max'     => 9999999999,
            'value'   => isset($data['num_posts_2']) ? $data['num_posts_2'] : null,
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
            'maxlength' => 100,
            'value'     => isset($data['last_post_1']) ? $data['last_post_1'] : null,
            'caption'   => \ForkBB\__('Last post label'),
        ];
        $fields['last_post_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($data['last_post_2']) ? $data['last_post_2'] : null,
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
            'maxlength' => 100,
            'value'     => isset($data['last_visit_1']) ? $data['last_visit_1'] : null,
            'caption'   => \ForkBB\__('Last visit label'),
        ];
        $fields['last_visit_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($data['last_visit_2']) ? $data['last_visit_2'] : null,
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
            'maxlength' => 100,
            'value'     => isset($data['registered_1']) ? $data['registered_1'] : null,
            'caption'   => \ForkBB\__('Registered label'),
        ];
        $fields['registered_2'] = [
            'class'     => 'bend',
            'type'      => 'text',
            'maxlength' => 100,
            'value'     => isset($data['registered_2']) ? $data['registered_2'] : null,
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $form['sets']['filters'] = [
            'legend' => \ForkBB\__('User search subhead'),
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
                'username'   => \ForkBB\__('Order by username'),
                'email'      => \ForkBB\__('Order by e-mail'),
                'num_posts'  => \ForkBB\__('Order by posts'),
                'last_post'  => \ForkBB\__('Order by last post'),
                'last_visit' => \ForkBB\__('Order by last visit'),
                'registered' => \ForkBB\__('Order by registered'),
            ],
            'value'   => isset($data['order_by']) ? $data['order_by'] : 'registered',
            'caption' => \ForkBB\__('Order by label'),
        ];
        $fields['direction'] = [
            'class'   => 'bend',
            'type'    => 'select',
            'options' => [
                'ASC'  => \ForkBB\__('Ascending'),
                'DESC' => \ForkBB\__('Descending'),
            ],
            'value'   => isset($data['direction']) ? $data['direction'] : 'DESC',
        ];
        $fields[] = [
            'type' => 'endwrap',
        ];
        $fields['user_group'] = [
            'type'    => 'select',
            'options' => $this->groups(),
            'value'   => isset($data['user_group']) ? $data['user_group'] : -1,
            'caption' => \ForkBB\__('User group label'),
        ];

        $form['sets']['sorting'] = [
            'legend' => \ForkBB\__('Search results legend'),
            'fields' => $fields,
        ];

        return $form;
    }

    /**
     * Создает массив данных для формы поиска по IP
     *
     * @param array $data
     *
     * @return array
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
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Find IP address'),
                    'accesskey' => 'f',
                ],
            ],
        ];
        $fields = [];
        $fields['ip'] = [
            'type'      => 'text',
            'maxlength' => 49,
            'caption'   => \ForkBB\__('IP address label'),
            'value'     => isset($data['ip']) ? $data['ip'] : null,
            'required'  => true,
        ];
        $form['sets']['ip'] = [
            'legend' => \ForkBB\__('IP search subhead'),
            'fields' => $fields,
        ];

        return $form;
    }
}
