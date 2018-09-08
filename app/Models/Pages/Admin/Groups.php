<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Group\Model as Group;
use ForkBB\Models\Pages\Admin;

class Groups extends Admin
{
    /**
     * Конструктор
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->c->Lang->load('admin_groups');

        $groupsList    = [];
        $groupsNew     = [];
        $groupsDefault = [];
        $notForNew     = [$this->c->GROUP_ADMIN];
        $notForDefault = [$this->c->GROUP_ADMIN, $this->c->GROUP_MOD, $this->c->GROUP_GUEST];

        foreach ($this->c->groups->getList() as $key => $group) {
            $groupsList[$key] = [$group->g_title, $group->linkEdit, $group->linkDelete];

            if (! \in_array($group->g_id, $notForNew)) {
                $groupsNew[$key] = $group->g_title;
            }
            if (! \in_array($group->g_id, $notForDefault) && $group->g_moderator == 0) {
                $groupsDefault[$key] = $group->g_title;
            }
        }

        $this->aIndex        = 'groups';
        $this->groupsList    = $groupsList;
        $this->groupsNew     = $groupsNew;
        $this->groupsDefault = $groupsDefault;
    }

    /**
     * Подготавливает данные для шаблона
     *
     * @return Page
     */
    public function view()
    {
        $this->nameTpl     = 'admin/groups';
        $this->formNew     = [
            'action' => $this->c->Router->link('AdminGroupsNew'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminGroupsNew'),
            ],
            'sets'   => [
                'base' => [
                    'legend' => \ForkBB\__('Add group subhead'),
                    'fields' => [
                        'basegroup' => [
                            'type'      => 'select',
                            'options'   => $this->groupsNew,
                            'value'     => $this->c->config->o_default_user_group,
                            'caption'   => \ForkBB\__('New group label'),
                            'info'      => \ForkBB\__('New group help'),
#                           'autofocus' => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Add'),
                    'accesskey' => 'a',
                ],
            ],
        ];
        $this->formDefault = [
            'action' => $this->c->Router->link('AdminGroupsDefault'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminGroupsDefault'),
            ],
            'sets'   => [
                'del' => [
                    'legend' => \ForkBB\__('Default group subhead'),
                    'fields' => [
                        'defaultgroup' => [
                            'type'    => 'select',
                            'options' => $this->groupsDefault,
                            'value'   => $this->c->config->o_default_user_group,
                            'caption' => \ForkBB\__('Default group label'),
                            'info'    => \ForkBB\__('Default group help'),
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Update'),
                    'accesskey' => 's',
                ],
            ],
        ];

        return $this;
    }

    /**
     * Устанавливает группу по умолчанию
     *
     * @return Page
     */
    public function defaultSet()
    {
        $v = $this->c->Validator->reset()
            ->addRules([
                'token'        => 'token:AdminGroupsDefault',
                'defaultgroup' => 'required|integer|in:' . \implode(',', \array_keys($this->groupsDefault)),
            ])->addAliases([
            ])->addMessages([
                'defaultgroup.in' => 'Invalid default group',
            ]);

        if (! $v->validation($_POST)) {
            $this->fIswev = $v->getErrors();
            return $this->view();
        }
        $this->c->config->o_default_user_group = $v->defaultgroup;
        $this->c->config->save();
        return $this->c->Redirect->page('AdminGroups')->message('Default group redirect');
    }

    /**
     * Редактирование группы
     * Создание новой группы
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function edit(array $args, $method)
    {
        // начало создания новой группы
        if (empty($args['id']) && empty($args['base'])) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:AdminGroupsNew',
                    'basegroup' => 'required|integer|in:' . \implode(',', \array_keys($this->groupsNew)),
                ])->addAliases([
                ])->addMessages([
                    'basegroup.in' => 'Invalid group to create on base',
                ]);

            if (! $v->validation($_POST)) {
                $this->fIswev = $v->getErrors();
                return $this->view();
            }

            $gid  = $v->basegroup;
            $next = false;
        // продолжение редактирования/создания
        } else {
            $gid  = (int) (isset($args['id']) ? $args['id'] : $args['base']);
            $next = true;
        }

        $baseGroup = $this->c->groups->get($gid);

        if (! $baseGroup instanceof Group) {
            return $this->c->Message->message('Bad request');
        }

        $group   = clone $baseGroup;
        $notNext = $this->c->GROUP_ADMIN . ',' . $this->c->GROUP_GUEST;

        if (isset($args['id'])) {
            $marker          = 'AdminGroupsEdit';
            $vars            = ['id' => $group->g_id];
            $notNext        .= ',' . $group->g_id;
            $this->aCrumbs[] = [$this->c->Router->link($marker, $vars), \ForkBB\__('Edit group')];
            $this->aCrumbs[] = \ForkBB\__('"%s"', $group->g_title);
            $this->titleForm = \ForkBB\__('Edit group');
            $this->classForm = 'editgroup';
        } else {
            $marker          = 'AdminGroupsNew';
            $vars            = ['base' => $group->g_id];
            $group->g_title  = '';
            $group->g_id     = null;
            $this->aCrumbs[] = \ForkBB\__('Create new group');
            $this->titleForm = \ForkBB\__('Create new group');
            $this->classForm = 'creategroup';
        }

        if ('POST' === $method && $next) {
            $reserve = [];
            foreach ($this->groupsList as $key => $cur) {
                if ($group->g_id !== $key) {
                    $reserve[] = $cur[0];
                }
            }

            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'                  => 'token:' . $marker,
                    'g_title'                => 'required|string:trim|max:50|not_in:' . \implode(',', $reserve),
                    'g_user_title'           => 'string:trim|max:50',
                ])->addAliases([
                ])->addArguments([
                    'token' => $vars,
                ])->addMessages([
                    'g_title.required' => 'You must enter a group title',
                    'g_title.not_in'   => 'Title already exists',
                ]);

            if (! $group->groupAdmin) {
                $v->addRules([
                    'g_promote_next_group'   => 'integer|min:0|not_in:' . $notNext,
                    'g_promote_min_posts'    => 'integer|min:0|max:9999999999',
                    'g_read_board'           => 'integer|in:0,1',
                    'g_view_users'           => 'integer|in:0,1',
                    'g_post_replies'         => 'integer|in:0,1',
                    'g_post_topics'          => 'integer|in:0,1',
                    'g_edit_posts'           => 'integer|in:0,1',
                    'g_delete_posts'         => 'integer|in:0,1',
                    'g_delete_topics'        => 'integer|in:0,1',
                    'g_deledit_interval'     => 'integer|min:0|max:999999',
                    'g_set_title'            => 'integer|in:0,1',
                    'g_post_links'           => 'integer|in:0,1',
                    'g_search'               => 'integer|in:0,1',
                    'g_search_users'         => 'integer|in:0,1',
                    'g_send_email'           => 'integer|in:0,1',
                    'g_post_flood'           => 'integer|min:0|max:999999',
                    'g_search_flood'         => 'integer|min:0|max:999999',
                    'g_email_flood'          => 'integer|min:0|max:999999',
                    'g_report_flood'         => 'integer|min:0|max:999999',
                ]);

                if (! $group->groupGuest && ! $group->groupMember && $group->g_id != $this->c->config->o_default_user_group) {
                    $v->addRules([
                        'g_moderator'            => 'integer|in:0,1',
                        'g_mod_edit_users'       => 'integer|in:0,1',
                        'g_mod_rename_users'     => 'integer|in:0,1',
                        'g_mod_change_passwords' => 'integer|in:0,1',
                        'g_mod_promote_users'    => 'integer|in:0,1',
                        'g_mod_ban_users'        => 'integer|in:0,1',
                    ]);
                }
            }

            if ($v->validation($_POST)) {
                return $this->save($group, $baseGroup, $v->getData());
            }

            $this->fIswev  = $v->getErrors();
            $group->replAttrs($v->getData());
        }

        $this->nameTpl = 'admin/form';
        $this->form    = $this->viewForm($group, $marker, $vars);

        return $this;
    }

    /**
     * Запись данных по новой/измененной группе
     *
     * @param Group $group
     * @param Group $baseGroup
     * @param array $args
     *
     * @return Page
     */
    public function save(Group $group, Group $baseGroup, array $data)
    {
        if (! $group->groupAdmin && isset($data['g_moderator']) && 0 === $data['g_moderator']) {
            $data['g_mod_edit_users']       = 0;
            $data['g_mod_rename_users']     = 0;
            $data['g_mod_change_passwords'] = 0;
            $data['g_mod_promote_users']    = 0;
            $data['g_mod_ban_users']        = 0;
        }
        if (isset($data['g_promote_next_group']) && $data['g_promote_next_group'] * $data['g_promote_min_posts'] == 0) {
            $data['g_promote_next_group'] = 0;
            $data['g_promote_min_posts']  = 0;
        }

        foreach ($data as $attr => $value) {
            $group->$attr = $value;
        }

        $this->c->DB->beginTransaction();

        if (null === $group->g_id) {
            $message = \ForkBB\__('Group added redirect');
            $this->c->groups->insert($group);

            $this->c->groups->Perm->copy($baseGroup, $group);
        } else {
            $message = \ForkBB\__('Group edited redirect');
            $this->c->groups->update($group);

            if ($group->g_promote_min_posts) {
                $this->c->users->promote($group);
            }
        }

        $this->c->DB->commit();

        $this->c->Cache->delete('forums_mark');

        return $this->c->Redirect->page('AdminGroupsEdit', ['id' => $group->g_id])->message($message);
    }

    /**
     * Формирует данные для формы редактирования группы
     *
     * @param Group $group
     * @param string $marker
     * @param array $args
     *
     * @return array
     */
    protected function viewForm(Group $group, $marker, array $args)
    {
        $form = [
            'action' => $this->c->Router->link($marker, $args),
            'hidden' => [
                'token' => $this->c->Csrf->create($marker, $args),
            ],
            'sets'   => [],
            'btns'   => [
                'submit'  => [
                    'type'      => 'submit',
                    'value'     => null === $group->g_id ? \ForkBB\__('Add') : \ForkBB\__('Update'),
                    'accesskey' => 's',
                ],
            ],
        ];

        if (! $group->groupAdmin) {
            $form['sets']['def-info'] = [
                'info' => [
                    'info1' => [
                        'type'  => '', //????
                        'value' => \ForkBB\__('Group settings info'),
                    ],
                ],
            ];
        }

        $fieldset = [];
        $fieldset['g_title'] = [
            'type'      => 'text',
            'maxlength' => 50,
            'value'     => $group->g_title,
            'caption'   => \ForkBB\__('Group title label'),
            'required'  => true,
#           'autofocus' => true,
        ];
        $fieldset['g_user_title'] = [
            'type'      => 'text',
            'maxlength' => 50,
            'value'     => $group->g_user_title,
            'caption'   => \ForkBB\__('User title label'),
            'info'      => \ForkBB\__('User title help', $group->groupGuest ? \ForkBB\__('Guest') : \ForkBB\__('Member')),
        ];

        if ($group->groupAdmin) {
            $form['sets']['group-data'] = [
                'fields' => $fieldset,
            ];
            return $form;
        }

        if (! $group->groupGuest) {
            $options = [0 => \ForkBB\__('Disable promotion')];

            foreach ($this->groupsNew as $key => $title) {
                if ($key === $group->g_id || $key === $this->c->GROUP_GUEST) {
                    continue;
                }
                $options[$key] = $title;
            }

            $fieldset['g_promote_next_group'] = [
                'type'    => 'select',
                'options' => $options,
                'value'   => $group->g_promote_next_group,
                'caption' => \ForkBB\__('Promote users label'),
                'info'    => \ForkBB\__('Promote users help', \ForkBB\__('Disable promotion')),
            ];
            $fieldset['g_promote_min_posts'] = [
                'type'    => 'number',
                'min'     => 0,
                'max'     => 9999999999,
                'value'   => $group->g_promote_min_posts,
                'caption' => \ForkBB\__('Number for promotion label'),
                'info'    => \ForkBB\__('Number for promotion help'),
            ];
        }

        $yn = [1 => \ForkBB\__('Yes'), 0 => \ForkBB\__('No')];

        if (! $group->groupGuest && ! $group->groupMember && $group->g_id != $this->c->config->o_default_user_group) {
            $fieldset['g_moderator'] = [
                'type'    => 'radio',
                'value'   => $group->g_moderator,
                'values'  => $yn,
                'caption' => \ForkBB\__('Mod privileges label'),
                'info'    => \ForkBB\__('Mod privileges help'),
            ];
            $fieldset['g_mod_edit_users'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_edit_users,
                'values'  => $yn,
                'caption' => \ForkBB\__('Edit profile label'),
                'info'    => \ForkBB\__('Edit profile help'),
            ];
            $fieldset['g_mod_rename_users'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_rename_users,
                'values'  => $yn,
                'caption' => \ForkBB\__('Rename users label'),
                'info'    => \ForkBB\__('Rename users help'),
            ];
            $fieldset['g_mod_change_passwords'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_change_passwords,
                'values'  => $yn,
                'caption' => \ForkBB\__('Change passwords label'),
                'info'    => \ForkBB\__('Change passwords help'),
            ];
            $fieldset['g_mod_promote_users'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_promote_users,
                'values'  => $yn,
                'caption' => \ForkBB\__('Mod promote users label'),
                'info'    => \ForkBB\__('Mod promote users help'),
            ];
            $fieldset['g_mod_ban_users'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_ban_users,
                'values'  => $yn,
                'caption' => \ForkBB\__('Ban users label'),
                'info'    => \ForkBB\__('Ban users help'),
            ];
        }

        $fieldset['g_read_board'] = [
            'type'    => 'radio',
            'value'   => $group->g_read_board,
            'values'  => $yn,
            'caption' => \ForkBB\__('Read board label'),
            'info'    => \ForkBB\__('Read board help'),
        ];
        $fieldset['g_view_users'] = [
            'type'    => 'radio',
            'value'   => $group->g_view_users,
            'values'  => $yn,
            'caption' => \ForkBB\__('View user info label'),
            'info'    => \ForkBB\__('View user info help'),
        ];
        $fieldset['g_post_replies'] = [
            'type'    => 'radio',
            'value'   => $group->g_post_replies,
            'values'  => $yn,
            'caption' => \ForkBB\__('Post replies label'),
            'info'    => \ForkBB\__('Post replies help'),
        ];
        $fieldset['g_post_topics'] = [
            'type'    => 'radio',
            'value'   => $group->g_post_topics,
            'values'  => $yn,
            'caption' => \ForkBB\__('Post topics label'),
            'info'    => \ForkBB\__('Post topics help'),
        ];

        if (! $group->groupGuest) {
            $fieldset['g_edit_posts'] = [
                'type'    => 'radio',
                'value'   => $group->g_edit_posts,
                'values'  => $yn,
                'caption' => \ForkBB\__('Edit posts label'),
                'info'    => \ForkBB\__('Edit posts help'),
            ];
            $fieldset['g_delete_posts'] = [
                'type'    => 'radio',
                'value'   => $group->g_delete_posts,
                'values'  => $yn,
                'caption' => \ForkBB\__('Delete posts label'),
                'info'    => \ForkBB\__('Delete posts help'),
            ];
            $fieldset['g_delete_topics'] = [
                'type'    => 'radio',
                'value'   => $group->g_delete_topics,
                'values'  => $yn,
                'caption' => \ForkBB\__('Delete topics label'),
                'info'    => \ForkBB\__('Delete topics help'),
            ];
            $fieldset['g_deledit_interval'] = [
                'type'    => 'number',
                'min'     => 0,
                'max'     => 999999,
                'value'   => $group->g_deledit_interval,
                'caption' => \ForkBB\__('Delete-edit interval label'),
                'info'    => \ForkBB\__('Delete-edit interval help'),
            ];
            $fieldset['g_set_title'] = [
                'type'    => 'radio',
                'value'   => $group->g_set_title,
                'values'  => $yn,
                'caption' => \ForkBB\__('Set own title label'),
                'info'    => \ForkBB\__('Set own title help'),
            ];
        }

        $fieldset['g_post_links'] = [
            'type'    => 'radio',
            'value'   => $group->g_post_links,
            'values'  => $yn,
            'caption' => \ForkBB\__('Post links label'),
            'info'    => \ForkBB\__('Post links help'),
        ];
        $fieldset['g_search'] = [
            'type'    => 'radio',
            'value'   => $group->g_search,
            'values'  => $yn,
            'caption' => \ForkBB\__('User search label'),
            'info'    => \ForkBB\__('User search help'),
        ];
        $fieldset['g_search_users'] = [
            'type'    => 'radio',
            'value'   => $group->g_search_users,
            'values'  => $yn,
            'caption' => \ForkBB\__('User list search label'),
            'info'    => \ForkBB\__('User list search help'),
        ];

        if (! $group->groupGuest) {
            $fieldset['g_send_email'] = [
                'type'    => 'radio',
                'value'   => $group->g_send_email,
                'values'  => $yn,
                'caption' => \ForkBB\__('Send e-mails label'),
                'info'    => \ForkBB\__('Send e-mails help'),
            ];
        }

        $fieldset['g_post_flood'] = [
            'type'    => 'number',
            'min'     => 0,
            'max'     => 999999,
            'value'   => $group->g_post_flood,
            'caption' => \ForkBB\__('Post flood label'),
            'info'    => \ForkBB\__('Post flood help'),
        ];
        $fieldset['g_search_flood'] = [
            'type'    => 'number',
            'min'     => 0,
            'max'     => 999999,
            'value'   => $group->g_search_flood,
            'caption' => \ForkBB\__('Search flood label'),
            'info'    => \ForkBB\__('Search flood help'),
        ];

        if (! $group->groupGuest) {
            $fieldset['g_email_flood'] = [
                'type'    => 'number',
                'min'     => 0,
                'max'     => 999999,
                'value'   => $group->g_email_flood,
                'caption' => \ForkBB\__('E-mail flood label'),
                'info'    => \ForkBB\__('E-mail flood help'),
            ];
            $fieldset['g_report_flood'] = [
                'type'    => 'number',
                'min'     => 0,
                'max'     => 999999,
                'value'   => $group->g_report_flood,
                'caption' => \ForkBB\__('Report flood label'),
                'info'    => \ForkBB\__('Report flood help'),
            ];
        }

        $form['sets']['group-data'] = [
            'fields' => $fieldset,
        ];

        if (! empty($group->g_moderator)) {
            $form['sets']['mod-info'] = [
                'info' => [
                    'info1' => [
                        'type'  => '', //????
                        'value' => \ForkBB\__('Moderator info'),
                    ],
                ],
            ];
        }

        return $form;
    }

    /**
     * Удаление группы
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function delete(array $args, $method)
    {
        $group = $this->c->groups->get((int) $args['id']);

        if (null === $group || ! $group->canDelete) {
            return $this->c->Message->message('Bad request');
        }

        $count = $this->c->users->UsersNumber($group);
        if ($count) {
            $move   = 'required|integer|in:';
            $groups = [];
            foreach ($this->groupsList as $key => $cur) {
                if ($key === $this->c->GROUP_GUEST || $key === $group->g_id) {
                    continue;
                }
                $groups[$key] = $cur[0];
            }
            $move  .= \implode(',', \array_keys($groups));
        } else {
            $move   = 'absent';
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:AdminGroupsDelete',
                    'movegroup' => $move,
                    'confirm'   => 'integer',
                    'delete'    => 'string',
                    'cancel'    => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (! $v->validation($_POST) || null === $v->delete) {
                return $this->c->Redirect->page('AdminGroups')->message('Cancel redirect');
            } elseif ($v->confirm !== 1) {
                return $this->c->Redirect->page('AdminGroups')->message('No confirm redirect');
            }

            $this->c->DB->beginTransaction();

            if ($v->movegroup) {
                $this->c->groups->delete($group, $this->c->groups->get($v->movegroup));
            } else {
                $this->c->groups->delete($group);
            }

            $this->c->DB->commit();

            return $this->c->Redirect->page('AdminGroups')->message('Group removed redirect');
        }

        $form = [
            'action' => $this->c->Router->link('AdminGroupsDelete', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminGroupsDelete', $args),
            ],
            'sets'   => [],
            'btns'   => [
                'delete' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Delete group'),
                    'accesskey' => 'd',
                ],
                'cancel' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Cancel'),
                ],
            ],
        ];

        if ($count) {
            $form['sets']['move'] = [
                'fields' => [
                    'movegroup' => [
                        'type'    => 'select',
                        'options' => $groups,
                        'value'   => $this->c->config->o_default_user_group,
                        'caption' => \ForkBB\__('Move users label'),
                        'info'    => \ForkBB\__('Move users info', $group->g_title, $count),
                    ],
                ],
            ];
        }

        $form['sets']['conf'] = [
            'fields' => [
                'confirm' => [
                    'caption' => \ForkBB\__('Confirm delete'),
                    'type'    => 'checkbox',
                    'label'   => \ForkBB\__('I want to delete this group', $group->g_title),
                    'value'   => '1',
                    'checked' => false,
                ],
            ],
        ];
        $form['sets']['conf-info'] = [
            'info' => [
                'info1' => [
                    'type'  => '', //????
                    'value' => \ForkBB\__('Confirm delete warn'),
                    'html'  => true,
                ],
            ],
        ];

        $this->nameTpl   = 'admin/form';
        $this->aCrumbs[] = [$this->c->Router->link('AdminGroupsDelete', $args), \ForkBB\__('Group delete')];
        $this->aCrumbs[] = \ForkBB\__('"%s"', $group->g_title);
        $this->form      = $form;
        $this->titleForm = \ForkBB\__('Group delete');
        $this->classForm = 'deletegroup';

        return $this;
    }
}
