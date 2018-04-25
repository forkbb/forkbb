<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Config\Model as Config;

class Users extends Admin
{
    /**
     * Генерирует список доступных групп пользователей
     *
     * @param bool $onlyKeys
     *
     * @return array
     */
    protected function groups($onlyKeys = false)
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
     * Подготавливает данные для шаблона найденных по фильтру пользователей
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function filter(array $args, $method)
    {
        if (! \hash_equals($args['hash'], $this->c->Secury->hash($args['filters']))
            || ! \is_array($data = \json_decode(\base64_decode($args['filters'], true), true))
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('admin_users');

        $order = [
            $data['order_by'] => $data['direction'],
        ];
        $filters = [];

        if ($data['user_group'] > -1) {
            $filters['group_id'] = ['=', $data['user_group']];
        }

        foreach ($data as $field => $value) {
            if ('order_by' === $field || 'direction' === $field || 'user_group' === $field) {
                continue;
            }

            $key  = 1;
            $type = '=';

            if (\preg_match('%^(.+?)_(1|2)$%', $field, $matches)) {
                $type  = 'BETWEEN';
                $field = $matches[1];
                $key   = $matches[2];

                if (\is_string($value)) {
                    $value = \strtotime($value . ' UTC');
                }
            } elseif (\is_string($value)) {
                $type  = 'LIKE';
            }

            $filters[$field][0]    = $type;
            $filters[$field][$key] = $value;
        }

        $ids    = $this->c->users->filter($filters, $order);
        $number = \count($ids);

        if (0 == $number) {
            $this->fIswev = ['i', \ForkBB\__('No users found')];

            return $this->view([], 'GET', $data);
        }

        $page  = isset($args['page']) ? (int) $args['page'] : 1;
        $pages = (int) \ceil($number / $this->c->config->o_disp_users);

        if ($page > $pages) {
            return $this->c->Message->message('Bad request');
        }

        $startNum = ($page - 1) * $this->c->config->o_disp_users;
        $ids      = \array_slice($ids, $this->startNum, $this->c->config->o_disp_users);
        $userList = $this->c->users->load($ids);

        $this->nameTpl    = 'admin/users_result';
        $this->aIndex     = 'users';
        $this->mainSuffix = '-one-column';
        $this->aCrumbs[]  = [$this->c->Router->link('AdminShowUsersWithFilter', ['filters' => $args['filters'], 'hash' => $args['hash']]), \ForkBB\__('Results head')];
        $this->formResult = $this->formUsers($userList, $startNum);

        return $this;
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
    public function view(array $args, $method, array $data = [])
    {
        $this->c->Lang->load('admin_users');

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
                    return $this->c->Redirect->page('AdminShowUsersWithIP', ['ip' => $ip]);
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
                    $filters = $v->getData();
                    unset($filters['token']);
                    $filters = \base64_encode(\json_encode($filters));
                    $hash    = $this->c->Secury->hash($filters);
                    return $this->c->Redirect->page('AdminShowUsersWithFilter', ['filters' => $filters, 'hash' => $hash]);
                }

                $this->fIswev = $v->getErrors();
                $data         = $v->getData();
            }
        }

        $this->nameTpl    = 'admin/users';
        $this->aIndex     = 'users';
        $this->formSearch = $this->formSearch($data);

        if ($this->user->isAdmin) {
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
    protected function formSearch(array $data)
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
    protected function formIP(array $data)
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

    /**
     * Создает массив данных для формы найденных по фильтру пользователей
     *
     * @param array $users
     * @param int $number
     *
     * @return array
     */
    protected function formUsers(array $users, $number)
    {
        $form = [
            'action' => $this->c->Router->link(''),
            'hidden' => [
                'token' => $this->c->Csrf->create(''),
            ],
            'sets'   => [],
            'btns'   => [
                'find1' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('???'),
                    'accesskey' => 's',
                ],
            ],
        ];

        \array_unshift($users, $this->c->users->create(['id' => -1]));

        foreach ($users as $user) {
            $fields = [];
            $fields["l{$number}-wrap1"] = [
                'class' => 'main-result',
                'type'  => 'wrap',
            ];
            $fields["l{$number}-wrap2"] = [
                'class' => 'user-result',
                'type'  => 'wrap',
            ];
            $fields["l{$number}-username"] = [
                'class'   => ['result', 'username'],
                'type'    => $user->isGuest ? 'str' : 'link',
                'caption' => \ForkBB\__('Results username head'),
                'value'   => $user->username,
                'href'    => $user->link,
            ];
            $fields["l{$number}-email"] = [
                'class'   => ['result', 'email'],
                'type'    => 'link',
                'caption' => \ForkBB\__('Results e-mail head'),
                'value'   => $user->email,
                'href'    => 'mailto:' . $user->email,
            ];
            $fields[] = [
                'type' => 'endwrap',
            ];
            $fields["l{$number}-title"] = [
                'class'   => ['result', 'title'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Results title head'),
                'value'   => -1 === $user->id ? null : $user->title(),
            ];
            $fields["l{$number}-posts"] = [
                'class'   => ['result', 'posts'],
                'type'    => $user->num_posts ? 'link' : 'str',
                'caption' => \ForkBB\__('Results posts head'),
                'value'   => $user->num_posts ? \ForkBB\num($user->num_posts) : null,
                'href'    => $this->c->Router->link('SearchAction', ['action' => 'posts', 'uid' => $user->id]),
                'title'   => \ForkBB\__('Results show posts link'),
            ];
            $fields["l{$number}-note"] = [
                'class'   => ['result', 'note'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Примечание админа'),
                'value'   => $user->admin_note,
            ];

            if ($this->user->isAdmin) {
                $fields["l{$number}-view-ip"] = [
                    'class'   => ['result', 'view-ip'],
                    'type'    => $user->isGuest ? 'str' : 'link',
                    'caption' => \ForkBB\__('Results action head'),
                    'value'   => $user->isGuest ? null : \ForkBB\__('Results view IP link'),
                    'href'    => '',
                ];
            }

            $fields[] = [
                'type' => 'endwrap',
            ];
            $key = $user->isGuest ? "guest{$number}" : "users[{$user->id}]";
            $fields[$key] = [
                'class'   => ['result', 'check'],
                'caption' => \ForkBB\__('Select'),
                'type'    => $user->isGuest ? 'str' : 'checkbox',
                'value'   => $user->isGuest ? null : $user->id,
                'checked' => false,
            ];
            $form['sets']["l{$number}"] = [
                'class'  => 'result',
                'legend' => -1 === $user->id ? null : $number,
                'fields' => $fields,
            ];

            ++$number;
        }

        return $form;
    }
}
