<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Page;
use ForkBB\Core\Validator;
use ForkBB\Models\Forum\Model as Forum;
use InvalidArgumentException;

class Userlist extends Page
{
    use CrumbTrait;

    protected $usersPerPage = 2; // ???? в конфиг!

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

        $ids    = $this->c->users->filter([], []);
        $number = \count($ids);
        $page   = isset($args['page']) ? (int) $args['page'] : 1;
        $pages  = $number ? (int) \ceil($number / $this->usersPerPage) : 1;

        if ($page > $pages) {
            return $this->c->Message->message('Bad request');
        }

        if ($number) {
            $this->startNum = ($page - 1) * $this->usersPerPage;
            $ids = \array_slice($ids, $this->startNum, $this->usersPerPage);
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
        $this->pagination   = $this->c->Func->paginate($pages, $page, 'Userlist');

        return $this;
    }
}
