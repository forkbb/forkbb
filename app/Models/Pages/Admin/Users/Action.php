<?php

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin\Users;
use RuntimeException;

class Action extends Users
{
    /**
     * Возвращает список имен пользователей
     *
     * @param array $users
     *
     * @return array
     */
    protected function nameList(array $users)
    {
        $result = [];
        foreach ($users as $user) {
            $result[] = $user->username;
        }
        \sort($result, \SORT_STRING | \SORT_FLAG_CASE);
        return $result;
    }

    /**
     * Подготавливает данные для шаблона(ов) действия
     *
     * @param array $args
     * @param string $method
     *
     * @throws RuntimeException
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
        $this->rules = $this->c->UsersRules->init();

        $error = false;
        switch ($args['action']) {
            case self::ACTION_BAN:
                if (! $this->rules->banUsers) {
                    $error = true;
                }
                break;
            case self::ACTION_DEL:
                if (! $this->rules->deleteUsers) {
                    $error = true;
                }
                break;
            case self::ACTION_CHG:
                if (! $this->rules->changeGroup) {
                    $error = true;
                }
                break;
            default:
                $error = true;
        }

        if ($error) {
            return $this->c->Message->message('Bad request');
        }

        $ids = $this->checkSelected(\explode('-', $args['ids']), $args['action']);
        if (false === $ids) {
            $message = $this->c->Message->message('Action not available');
            $message->fIswev = $this->fIswev; //????
            return $message;
        }

        $this->userList = $this->c->users->load($ids);
        switch ($args['action']) {
            case self::ACTION_BAN:
                return $this->ban($args, $method);
            case self::ACTION_DEL:
                return $this->delete($args, $method);
            case self::ACTION_CHG:
                return $this->change($args, $method);
            default:
                throw new RuntimeException("The action {$args['action']} is unavailable");
        }
    }

    /**
     * Удаляет пользователей
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    protected function delete(array $args, $method)
    {
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'        => 'token:AdminUsersAction',
                    'confirm'      => 'required|integer|in:0,1',
                    'delete_posts' => 'required|integer|in:0,1',
                    'delete'       => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (! $v->validation($_POST) || $v->confirm !== 1) {
                return $this->c->Redirect->page('AdminUsers')->message('No confirm redirect');
            }

            if (1 === $v->delete_posts) {
                foreach ($this->userList as $user) {
                    $user->__deleteAllPost = true;
                }
            }

            $this->c->DB->beginTransaction();

            $this->c->users->delete(...$this->userList);

            $this->c->DB->commit();

            $this->c->Cache->delete('stats');       //???? перенести в manager
            $this->c->Cache->delete('forums_mark'); //???? с авто обновлением кеша

            return $this->c->Redirect->page('AdminUsers')->message('Users delete redirect');
        }

        $this->nameTpl    = 'admin/form';
        $this->classForm  = 'delete-users';
        $this->titleForm  = \ForkBB\__('Deleting users');
        $this->aCrumbs[]  = [$this->c->Router->link('AdminUsersAction', $args), \ForkBB\__('Deleting users')];
        $this->form       = $this->formDelete($args);

        return $this;
    }

    /**
     * Создает массив данных для формы удаления пользователей
     *
     * @param array $stat
     * @param int $number
     *
     * @return array
     */
    protected function formDelete(array $args)
    {
        $yn    = [1 => \ForkBB\__('Yes'), 0 => \ForkBB\__('No')];
        $names = \implode(', ', $this->nameList($this->userList));
        $form  = [
            'action' => $this->c->Router->link('AdminUsersAction', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUsersAction', $args),
            ],
            'sets'   => [
                'options' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'radio',
                            'value'   => 0,
                            'values'  => $yn,
                            'caption' => \ForkBB\__('Delete users'),
                            'info'    => \ForkBB\__('Confirm delete info', $names),
                        ],
                        'delete_posts' => [
                            'type'    => 'radio',
                            'value'   => 0,
                            'values'  => $yn,
                            'caption' => \ForkBB\__('Delete posts'),
                        ],
                    ],
                ],
                'info2' => [
                    'info' => [
                        'info2' => [
                            'type'    => '', //????
                            'value'   => \ForkBB\__('Delete warning'),
                            'html'    => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Delete users'),
                    'accesskey' => 'd',
                ],
                'cancel'  => [
                    'type'      => 'btn',
                    'value'     => \ForkBB\__('Cancel'),
                    'link'      => $this->c->Router->link('AdminUsers'),
                ],
            ],
        ];

        return $form;
    }

    /**
     * Возвращает список групп доступных для замены
     *
     * @return array
     */
    protected function groupListForChange()
    {
        $list = [];
        foreach ($this->c->groups->getList() as $id => $group) {
            if (! $group->groupGuest && ! $group->groupAdmin) {
                $list[$id] = $group->g_title;
            }
        }
        return $list;
    }

    /**
     * Изменяет группу пользователей
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    protected function change(array $args, $method)
    {
        if ('POST' === $method) {
            $groupList = \implode(',', \array_keys($this->groupListForChange()));
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:AdminUsersAction',
                    'new_group' => 'required|integer|in:' . $groupList,
                    'confirm'   => 'required|integer|in:0,1',
                    'move'      => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (! $v->validation($_POST) || $v->confirm !== 1) {
                return $this->c->Redirect->page('AdminUsers')->message('No confirm redirect');
            }

            $this->c->DB->beginTransaction();

            $this->c->users->changeGroup($v->new_group, ...$this->userList);

            $this->c->DB->commit();

            $this->c->Cache->delete('stats');       //???? перенести в manager
            $this->c->Cache->delete('forums_mark'); //???? с авто обновлением кеша

            return $this->c->Redirect->page('AdminUsers')->message('Users move redirect');
        }

        $this->nameTpl    = 'admin/form';
        $this->classForm  = 'change-group';
        $this->titleForm  = \ForkBB\__('Change user group');
        $this->aCrumbs[]  = [$this->c->Router->link('AdminUsersAction', $args), \ForkBB\__('Change user group')];
        $this->form       = $this->formChange($args);

        return $this;
    }

    /**
     * Создает массив данных для формы изменения группы пользователей
     *
     * @param array $stat
     * @param int $number
     *
     * @return array
     */
    protected function formChange(array $args)
    {
        $yn    = [1 => \ForkBB\__('Yes'), 0 => \ForkBB\__('No')];
        $names = \implode(', ', $this->nameList($this->userList));
        $form  = [
            'action' => $this->c->Router->link('AdminUsersAction', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUsersAction', $args),
            ],
            'sets'   => [
                'options' => [
                    'fields' => [
                        'new_group' => [
                            'type'      => 'select',
                            'options'   => $this->groupListForChange(),
                            'value'     => $this->c->config->o_default_user_group,
                            'caption'   => \ForkBB\__('New group label'),
                            'info'      => \ForkBB\__('New group help', $names),
                        ],
                        'confirm' => [
                            'type'    => 'radio',
                            'value'   => 0,
                            'values'  => $yn,
                            'caption' => \ForkBB\__('Move users'),
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'move'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Move users'),
                    'accesskey' => 'm',
                ],
                'cancel'  => [
                    'type'      => 'btn',
                    'value'     => \ForkBB\__('Cancel'),
                    'link'      => $this->c->Router->link('AdminUsers'),
                ],
            ],
        ];

        return $form;
    }
}
