<?php

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin\Users;

class Result extends Users
{
    /**
     * Подготавливает данные для шаблона найденных пользователей
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
        $data = $this->decodeData($args['data']);
        if (false === $data) {
            return $this->c->Message->message('Bad request');
        }

        if (isset($data['ip'])) {
            if (! $this->user->canViewIP) {
                return $this->c->Message->message('Bad request');
            }

            $ids = $this->forIP($data['ip']);
            $crName = $data['ip'];
        } else {
            $ids = $this->forFilter($data);
            $crName = \ForkBB\__('Results head');
        }

        $number = \count($ids);
        if (0 == $number) {
            $view = $this->c->AdminUsers;
            $view->fIswev = ['i', \ForkBB\__('No users found')];

            return $view->view([], 'GET', $data);
        }

        $page  = isset($args['page']) ? (int) $args['page'] : 1;
        $pages = (int) \ceil(($number ?: 1) / $this->c->config->o_disp_users);

        if ($page > $pages) {
            return $this->c->Message->message('Bad request');
        }

        $startNum = ($page - 1) * $this->c->config->o_disp_users;
        $ids      = \array_slice($ids, $startNum, $this->c->config->o_disp_users);
        $userList = $this->c->users->load($ids);

        $this->nameTpl    = 'admin/users_result';
        $this->aIndex     = 'users';
        $this->mainSuffix = '-one-column';
        $this->aCrumbs[]  = [$this->c->Router->link('AdminUsersResult', ['data' => $args['data']]), $crName];
        $this->formResult = $this->form($userList, $startNum);
        $this->pagination = $this->c->Func->paginate($pages, $page, 'AdminUsersResult', ['data' => $args['data']]);

        return $this;
    }

    /**
     * Возвращает список id пользователей по ip
     *
     * @param string $ip
     *
     * @return array
     */
    protected function forIP($ip)
    {
        $fromPosts = $this->c->posts->userInfoFromIP($ip);
        $ids       = $this->c->users->filter([
            'registration_ip' => ['=', $ip],
        ]);
        $ids       = \array_flip($ids);

        foreach ($fromPosts as $val) {
            if (isset($ids[$val])) {
                unset($ids[$val]);
            }
        }

        $ids = \array_flip($ids);
        return \array_merge($fromPosts, $ids);
    }

    /**
     * Возвращает список id пользователей по фильтру
     *
     * @param array $data
     *
     * @return array
     */
    protected function forFilter(array $data)
    {
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

        return $this->c->users->filter($filters, $order);
    }

    /**
     * Создает массив данных для формы найденных по фильтру пользователей
     *
     * @param array $users
     * @param int $number
     *
     * @return array
     */
    protected function form(array $users, $number)
    {
        $form = [
            'action' => $this->c->Router->link(''),
            'hidden' => [
                'token' => $this->c->Csrf->create(''),
            ],
            'sets'   => [],
            'btns'   => [
                'ban' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Ban'),
                    'accesskey' => null,
                ],
                'delete' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Delete'),
                    'accesskey' => null,
                ],
                'change_group' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Change group'),
                    'accesskey' => null,
                ],
            ],
        ];

        \array_unshift($users, $this->c->users->create(['id' => -1]));

        foreach ($users as $user) {
            if (\is_string($user)) {
                $user = $this->c->users->create(['id' => 1, 'username' => $user]);
            }

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
                'class'   => $user->isGuest ? ['result', 'email', 'no-data'] : ['result', 'email'],
                'type'    => $user->isGuest ? 'str' : 'link',
                'caption' => \ForkBB\__('Results e-mail head'),
                'value'   => $user->isGuest ? '' : $user->email,
                'href'    => $user->isGuest ? '' : 'mailto:' . $user->email,
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
                'class'   => $user->isGuest ? ['result', 'posts', 'no-data'] : ['result', 'posts'],
                'type'    => $user->num_posts ? 'link' : 'str',
                'caption' => \ForkBB\__('Results posts head'),
                'value'   => $user->num_posts ? \ForkBB\num($user->num_posts) : null,
                'href'    => $this->c->Router->link('SearchAction', ['action' => 'posts', 'uid' => $user->id]),
                'title'   => \ForkBB\__('Results show posts link'),
            ];
            $fields["l{$number}-note"] = [
                'class'   => '' === \trim($user->admin_note) ? ['result', 'note', 'no-data'] : ['result', 'note'],
                'type'    => 'str',
                'caption' => \ForkBB\__('Примечание админа'),
                'value'   => $user->admin_note,
            ];

            if ($this->user->isAdmin) {
                $fields["l{$number}-view-ip"] = [
                    'class'   => $user->isGuest ? ['result', 'view-ip', 'no-data'] : ['result', 'view-ip'],
                    'type'    => $user->isGuest || ! $user->num_posts ? 'str' : 'link',
                    'caption' => \ForkBB\__('Results action head'),
                    'value'   => $user->isGuest ? null : \ForkBB\__('Results view IP link'),
                    'href'    => $this->c->Router->link('AdminUserStat', ['id' => $user->id]),
                ];
            }

            $fields[] = [
                'type' => 'endwrap',
            ];
            $key = $user->isGuest ? "guest{$number}" : "users[{$user->id}]";
            $fields[$key] = [
                'class'   => ['check'],
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
