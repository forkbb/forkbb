<?php

namespace ForkBB\Models\Pages\Admin\Users;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Users;
use RuntimeException;
use function \ForkBB\__;

class Action extends Users
{
    /**
     * Возвращает список имен пользователей
     */
    protected function nameList(array $users): array
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
     */
    public function view(array $args, string $method): Page
    {
        if (isset($args['token'])) {
            if (! $this->c->Csrf->verify($args['token'], 'AdminUsersAction', $args)) {
                return $this->c->Message->message($this->c->Csrf->getError());
            }
            $profile = true;
        } else {
            $profile = false;
        }

        $error = false;
        switch ($args['action']) {
/*
            case self::ACTION_BAN:
                if (! $this->c->userRules->banUsers) {
                    $error = true;
                }
                break;
*/
            case self::ACTION_DEL:
                if (! $this->c->userRules->deleteUsers) {
                    $error = true;
                }
                break;
            case self::ACTION_CHG:
                if (
                    $profile
                    && ! $this->c->userRules->canChangeGroup($this->c->users->load((int) $args['ids']), true)
                ) {
                    $error = true;
                } elseif (
                    ! $profile
                    && ! $this->c->userRules->changeGroup
                ) {
                    $error = true;
                }
                break;
            default:
                $error = true;
        }

        if ($error) {
            return $this->c->Message->message('Bad request');
        }

        $ids = $this->checkSelected(\explode('-', $args['ids']), $args['action'], $profile);
        if (false === $ids) {
            $message = $this->c->Message->message('Action not available');
            $message->fIswev = $this->fIswev; //????

            return $message;
        }

        $this->userList = $this->c->users->loadByIds($ids);
        switch ($args['action']) {
/*
            case self::ACTION_BAN:
                return $this->ban($args, $method);
*/
            case self::ACTION_DEL:
                return $this->delete($args, $method);
            case self::ACTION_CHG:
                return $this->change($args, $method, $profile);
            default:
                throw new RuntimeException("The action {$args['action']} is unavailable");
        }
    }

    /**
     * Удаляет пользователей
     */
    protected function delete(array $args, string $method): Page
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

            if (
                ! $v->validation($_POST)
                || 1 !== $v->confirm
            ) {
                return $this->c->Redirect->page('AdminUsers')->message('No confirm redirect');
            }

            if (1 === $v->delete_posts) {
                foreach ($this->userList as $user) {
                    $user->__deleteAllPost = true;
                }
            }

            $this->c->users->delete(...$this->userList);

            $this->c->forums->reset();

            return $this->c->Redirect->page('AdminUsers')->message('Users delete redirect');
        }

        $this->nameTpl    = 'admin/form';
        $this->classForm  = 'delete-users';
        $this->titleForm  = __('Deleting users');
        $this->aCrumbs[]  = [
            $this->c->Router->link(
                'AdminUsersAction',
                $args
            ),
            __('Deleting users'),
        ];
        $this->form       = $this->formDelete($args);

        return $this;
    }

    /**
     * Создает массив данных для формы удаления пользователей
     */
    protected function formDelete(array $args): array
    {
        $yn    = [1 => __('Yes'), 0 => __('No')];
        $names = \implode(', ', $this->nameList($this->userList));
        $form  = [
            'action' => $this->c->Router->link(
                'AdminUsersAction',
                $args
            ),
            'hidden' => [
                'token' => $this->c->Csrf->create(
                    'AdminUsersAction',
                    $args
                ),
            ],
            'sets'   => [
                'options' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'radio',
                            'value'   => 0,
                            'values'  => $yn,
                            'caption' => __('Delete users'),
                            'info'    => __('Confirm delete info', $names),
                        ],
                        'delete_posts' => [
                            'type'    => 'radio',
                            'value'   => 0,
                            'values'  => $yn,
                            'caption' => __('Delete posts'),
                        ],
                    ],
                ],
                'info2' => [
                    'info' => [
                        'info2' => [
                            'type'    => '', //????
                            'value'   => __('Delete warning'),
                            'html'    => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete'  => [
                    'type'      => 'submit',
                    'value'     => __('Delete users'),
//                    'accesskey' => 'd',
                ],
                'cancel'  => [
                    'type'      => 'btn',
                    'value'     => __('Cancel'),
                    'link'      => $this->c->Router->link('AdminUsers'),
                ],
            ],
        ];

        return $form;
    }

    /**
     * Возвращает список групп доступных для замены
     */
    protected function groupListForChange(bool $profile): array
    {
        $list = [];
        foreach ($this->c->groups->getList() as $id => $group) {
                $list[$id] = $group->g_title;
        }
        unset($list[$this->c->GROUP_GUEST]);
        if (! $profile) {
            unset($list[$this->c->GROUP_ADMIN]);
        } elseif (! $this->user->isAdmin) {
            $list = [$this->c->GROUP_MEMBER => $list[$this->c->GROUP_MEMBER]];
        }

        return $list;
    }

    /**
     * Изменяет группу пользователей
     */
    protected function change(array $args, string $method, bool $profile): Page
    {
        $rulePass = 'absent';

        if ($profile) {
            $user = $this->c->users->load((int) $args['ids']);
            $link = $this->c->Router->link(
                'EditUserProfile',
                [
                    'id' => $user->id,
                ]
            );

            if (
                $user->isAdmin
                || $user->id === $this->user->id
            ) {
                $rulePass = 'required|string:trim|check_password';
            }
        } else {
            $link = $this->c->Router->link('AdminUsers');
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_password' => [$this, 'vCheckPassword'],
                ])->addRules([
                    'token'     => 'token:AdminUsersAction',
                    'new_group' => 'required|integer|in:' . \implode(',', \array_keys($this->groupListForChange($profile))),
                    'confirm'   => 'required|integer|in:0,1',
                    'password'  => $rulePass,
                    'move'      => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            $redirect = $this->c->Redirect;

            if ($v->validation($_POST)) {
                if (1 !== $v->confirm) {
                    return $redirect->url($link)->message('No confirm redirect');
                }

                $this->c->users->changeGroup($v->new_group, ...$this->userList);

                $this->c->forums->reset();

                if ($profile) {
                    if ($this->c->ProfileRules->setUser($user)->editProfile) {
                        $redirect->url($link);
                    } else {
                        $redirect->page('User', ['id' => $user->id, 'name' => $user->username]);
                    }
                } else {
                    $redirect->page('AdminUsers');
                }

                return $redirect->message('Users move redirect');

            }

            $this->fIswev = $v->getErrors();
        }

        $this->nameTpl    = 'admin/form';
        $this->classForm  = 'change-group';
        $this->titleForm  = __('Change user group');
        $this->aCrumbs[]  = [
            $this->c->Router->link(
                'AdminUsersAction',
                $args
            ),
            __('Change user group'),
        ];
        $this->form       = $this->formChange($args, $profile, $link, 'absent' !== $rulePass);

        return $this;
    }

    /**
     * Проверяет пароль на совпадение с текущим пользователем
     */
    public function vCheckPassword(Validator $v, $password)
    {
        if (! \password_verify($password, $this->user->password)) {
            $v->addError('Invalid passphrase');
        }

        return $password;
    }

    /**
     * Создает массив данных для формы изменения группы пользователей
     */
    protected function formChange(array $args, bool $profile, string $linkCancel, bool $checkPass): array
    {
        $yn    = [1 => __('Yes'), 0 => __('No')];
        $names = \implode(', ', $this->nameList($this->userList));
        $form  = [
            'action' => $this->c->Router->link(
                'AdminUsersAction',
                $args
            ),
            'hidden' => [
                'token' => $this->c->Csrf->create(
                    'AdminUsersAction',
                    $args
                ),
            ],
            'sets'   => [
                'options' => [
                    'fields' => [
                        'new_group' => [
                            'type'      => 'select',
                            'options'   => $this->groupListForChange($profile),
                            'value'     => $this->c->config->o_default_user_group,
                            'caption'   => __('New group label'),
                            'info'      => __('New group help', $names),
                        ],
                        'confirm' => [
                            'type'    => 'radio',
                            'value'   => 0,
                            'values'  => $yn,
                            'caption' => __('Move users'),
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'move'  => [
                    'type'      => 'submit',
                    'value'     => __('Move users'),
//                    'accesskey' => 'm',
                ],
                'cancel'  => [
                    'type'      => 'btn',
                    'value'     => __('Cancel'),
                    'link'      => $linkCancel,
                ],
            ],
        ];

        if ($checkPass) {
            $form['sets']['options']['fields']['password'] = [
                'type'      => 'password',
                'caption'   => __('Your passphrase'),
                'required'  => true,
            ];
        }

        return $form;
    }
}
