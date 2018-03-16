<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Core\Validator;
use ForkBB\Models\Forum\Model as Forum;
use InvalidArgumentException;

class Userlist extends Page
{
    use CrumbTrait;

    /**
     * Список пользователей
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
        $this->c->Lang->load('userlist');

        $groups = \array_filter($this->c->groups->getList(), function ($group) {
                return ! $group->groupGuest;
            });

        $prefix = 'POST' === $method ? 'required|' : '';
        $v = $this->c->Validator->reset()
            ->addRules([
                'sort'  => $prefix . 'string|in:username,registered' . ($this->user->showPostCount ? ',num_posts' : ''),
                'dir'   => $prefix . 'string|in:ASC,DESC',
                'group' => $prefix . 'integer|in:-1,' . \implode(',', \array_keys($groups)),
                'name'  => $prefix . 'string:trim|min:1|max:25' . ($this->user->searchUsers ? '' : '|in:*'),
            ]);

        $error = true;
        if ($v->validation('POST' === $method ? $_POST : $args)) {
            $count = (int) (null === $v->sort)
                   + (int) (null === $v->dir)
                   + (int) (null === $v->group)
                   + (int) (null === $v->name);

            if (0 === $count || 4 === $count) {
                $error = false;
            }
        }
        if ($error) {
            return $this->c->Message->message('Bad request');
        }
        if ('POST' === $method) {
            return $this->c->Redirect->page('Userlist', $v->getData());
        }

        $filters = [];
        if ($v->group < 1) {
            $filters['group_id'] = ['!=', 0];
        } else {
            $filters['group_id'] = ['=', $v->group];
        }
        if (null !== $v->name && '*' !== $v->name) {
            $filters['username'] = ['LIKE', $v->name];
        }

        $order  = $v->sort ? [$v->sort => $v->dir] : [];

        $ids    = $this->c->users->filter($filters, $order);
        $number = \count($ids);
        $page   = isset($args['page']) ? (int) $args['page'] : 1;
        $pages  = $number ? (int) \ceil($number / $this->c->config->o_disp_users) : 1;

        if ($page > $pages) {
            return $this->c->Message->message('Bad request');
        }

        if ($number) {
            $this->startNum = ($page - 1) * $this->c->config->o_disp_users;
            $ids = \array_slice($ids, $this->startNum, $this->c->config->o_disp_users);
            $this->userList = $this->c->users->load($ids);

            $links = [];
            $vars = ['page' => $page];

            if (4 === $count) {
                $vars['group'] = -1;
                $vars['name']  = '*';
            } else {
                $vars['group'] = $v->group;
                $vars['name']  = $v->name;
            }

            $this->activeLink = 0;

            foreach (['username', 'num_posts', 'registered'] as $i => $sort) {
                $vars['sort'] = $sort;

                foreach (['ASC', 'DESC'] as $j => $dir) {
                    $vars['dir'] = $dir;
                    $links[$i * 2 + $j] = $this->c->Router->link('Userlist', $vars);

                    if ($v->sort === $sort && $v->dir === $dir) {
                        $this->activeLink = $i * 2 + $j;
                    }
                }
            }

            $this->links = $links;
        } else {
            $this->startNum = 0;
            $this->userList = null;
            $this->links    = [null, null, null, null, null, null];
            $this->fIswev   = ['i', \ForkBB\__('No users found')];
        }

        $form = [
            'action' => $this->c->Router->link('Userlist'),
            'hidden' => [],
            'sets'   => [],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__($this->user->searchUsers ? 'Search btn' : 'Submit'),
                    'accesskey' => 's',
                ],
            ],
        ];

        $fields = [];

        if ($this->user->searchUsers) {
            $fields['name'] = [
                'type'      => 'text',
                'maxlength' => 25,
                'value'     => $v->name ?: '*',
                'title'     => \ForkBB\__('Username'),
                'info'      => \ForkBB\__('User search info'),
                'required'  => true,
#               'autofocus' => true,
            ];
        } else {
            $form['hidden']['name'] = '*';
        }
        $fields['group'] = [
            'class'   => 'w4',
            'type'    => 'select',
            'options' => [[-1, \ForkBB\__('All users')]] + \array_map(function ($group) {
                    return [$group->g_id, $group->g_title];
                }, $groups),
            'value'   => $v->group,
            'title'   => \ForkBB\__('User group'),
        ];
        $fields['sort'] = [
            'class'   => 'w4',
            'type'    => 'select',
            'options' => [
                ['username', \ForkBB\__('Sort by name')],
                ['num_posts', \ForkBB\__('Sort by number'), $this->user->showPostCount ? null : true],
                ['registered', \ForkBB\__('Sort by date')],
            ],
            'value'   => $v->sort,
            'title'   => \ForkBB\__('Sort users by'),
        ];
        $fields['dir'] = [
            'class'  => 'w4',
            'type'   => 'radio',
            'value'  => $v->dir ?: 'ASC',
            'values' => [
                'ASC'  => \ForkBB\__('Ascending'),
                'DESC' => \ForkBB\__('Descending'),
            ],
            'title'  => \ForkBB\__('User sort order'),
        ];
        $form['sets'][] = ['fields' => $fields];

        $this->fIndex       = 'userlist';
        $this->nameTpl      = 'userlist';
        $this->onlinePos    = 'userlist';
        $this->canonical    = $this->c->Router->link('Userlist', $args); // ????
        $this->robots       = 'noindex';
        $this->form         = $form;
        $this->crumbs       = $this->crumbs([$this->c->Router->link('Userlist'), \ForkBB\__('User_list')]);
        $this->pagination   = $this->c->Func->paginate($pages, $page, 'Userlist', $args);

        return $this;
    }
}
