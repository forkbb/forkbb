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

        $v = $this->c->Validator->reset()
            ->addRules([
                'sort'  => 'string|in:username,registered,num_posts',
                'dir'   => 'string|in:ASC,DESC',
                'group' => 'integer|min:-1|max:9999999999|not_in:0,' . $this->c->GROUP_GUEST,
                'name'  => 'string:trim|min:1|max:25',
            ]);

        $error = true;
        if ($v->validation($args)) {
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
        } else {
            $this->startNum = 0;
            $this->userList = null;
            // ни чего не найдено
        }

        $this->fIndex       = 'userlist';
        $this->nameTpl      = 'userlist';
        $this->onlinePos    = 'userlist';
        $this->canonical    = $this->c->Router->link('Userlist', ['page' => $page]); // ????
        $this->robots       = 'noindex';
//        $this->form         = $form;
        $this->crumbs       = $this->crumbs([$this->c->Router->link('Userlist'), \ForkBB\__('User_list')]);
        $this->pagination   = $this->c->Func->paginate($pages, $page, 'Userlist', $args);

        return $this;
    }
}
