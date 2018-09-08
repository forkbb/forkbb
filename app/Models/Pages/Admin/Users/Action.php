<?php

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin\Users;
use RuntimeException;

class Action extends Users
{
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


        $this->nameTpl    = 'admin/form';
        $this->classForm  = 'delete-users';
        $this->titleForm  = \ForkBB\__('Deleting users');
        $this->aCrumbs[]  = [$this->c->Router->link('AdminUsersAction', $args), \ForkBB\__('Deleting users')];
        $this->form       = $this->formDelete($args);

        return $this;

    }

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
     * Создает массив данных для формы статистики пользователя по ip
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
                'info' => [
                    'info' => [
                        'info1' => [
                            'type'    => '', //????
                            'value'   => \ForkBB\__('Confirm delete info', $names),
                        ],
                    ],
                ],
                'options' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'radio',
                            'value'   => 0,
                            'values'  => $yn,
                            'caption' => \ForkBB\__('Delete users'),
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
}
