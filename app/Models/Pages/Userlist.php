<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Forum\Model as Forum;
use InvalidArgumentException;

class Userlist extends Page
{
    /**
     * Возвращает список доступных групп
     *
     * @return array
     */
    protected function getgroupList(): array
    {
        $list = [
            'all' => \ForkBB\__('All users'),
        ];

        foreach ($this->c->groups->getList() as $group) {
            if (! $group->groupGuest) {
                $list[$group->g_id] = $group->g_title;
            }
        }

        return $list;
    }

    /**
     * Список пользователей
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('userlist');

        $prefix = 'POST' === $method ? 'required|' : '';
        $v = $this->c->Validator->reset()
            ->addRules([
                'sort'  => $prefix . 'string|in:username,registered' . ($this->user->showPostCount ? ',num_posts' : ''),
                'dir'   => $prefix . 'string|in:ASC,DESC',
                'group' => $prefix . 'string|in:' . \implode(',', \array_keys($this->groupList)),
                'name'  => $prefix . 'string|min:1|max:25' . ($this->user->searchUsers ? '' : '|in:*'),
            ]);

        $error = true;
        if ($v->validation('POST' === $method ? $_POST : $args)) {
            $count = (int) (null === $v->sort)
                   + (int) (null === $v->dir)
                   + (int) (null === $v->group)
                   + (int) (null === $v->name);

            if (
                0 === $count
                || 4 === $count
            ) {
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
        if (\is_numeric($v->group)) {
            $filters['group_id'] = ['=', $v->group];
        } else {
            $filters['group_id'] = ['!=', 0];
        }
        if (null !== $v->name) {
            $filters['username'] = ['LIKE', $v->name];
        }

        $order  = $v->sort ? [$v->sort => $v->dir] : [];

        $ids    = $this->c->users->filter($filters, $order);
        $number = \count($ids);
        $page   = isset($args['page']) ? (int) $args['page'] : 1;
        $pages  = (int) \ceil(($number ?: 1) / $this->c->config->o_disp_users);

        if ($page > $pages) {
            return $this->c->Message->message('Bad request');
        }

        if ($number) {
            $this->startNum = ($page - 1) * $this->c->config->o_disp_users;
            $ids = \array_slice($ids, $this->startNum, $this->c->config->o_disp_users);
            $this->userList = $this->c->users->loadByIds($ids);

            $links = [];
            $vars = ['page' => $page];

            if (4 === $count) {
                $vars['group'] = 'all';
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

                    if (
                        $v->sort === $sort
                        && $v->dir === $dir
                    ) {
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

        $this->fIndex       = 'userlist';
        $this->nameTpl      = 'userlist';
        $this->onlinePos    = 'userlist';
        $this->canonical    = $this->c->Router->link('Userlist', $args);
        $this->robots       = 'noindex';
        $this->crumbs       = $this->crumbs([$this->c->Router->link('Userlist'), \ForkBB\__('User list')]);
        $this->pagination   = $this->c->Func->paginate($pages, $page, 'Userlist', $args);
        $this->form         = $this->formUserlist($v);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param Validator $v
     *
     * @return array
     */
    protected function formUserlist(Validator $v): array
    {
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
                'class'     => 'w0',
                'type'      => 'text',
                'maxlength' => 25,
                'value'     => $v->name ?: '*',
                'caption'   => \ForkBB\__('Username'),
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
            'options' => $this->groupList,
            'value'   => $v->group,
            'caption' => \ForkBB\__('User group'),
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
            'caption' => \ForkBB\__('Sort users by'),
        ];
        $fields['dir'] = [
            'class'   => 'w4',
            'type'    => 'radio',
            'value'   => $v->dir ?: 'ASC',
            'values'  => [
                'ASC'  => \ForkBB\__('Ascending'),
                'DESC' => \ForkBB\__('Descending'),
            ],
            'caption' => \ForkBB\__('User sort order'),
        ];
        $form['sets']['users'] = ['fields' => $fields];

        return $form;
    }
}
