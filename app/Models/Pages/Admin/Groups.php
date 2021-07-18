<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Page;
use ForkBB\Models\Group\Model as Group;
use ForkBB\Models\Pages\Admin;
use function \ForkBB\__;

class Groups extends Admin
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_groups');

        $groupsList    = [];
        $groupsNew     = [];
        $groupsDefault = [];
        $notForNew     = [$this->c->GROUP_ADMIN];
        $notForDefault = [$this->c->GROUP_ADMIN, $this->c->GROUP_MOD, $this->c->GROUP_GUEST];

        foreach ($this->c->groups->getList() as $key => $group) {
            $groupsList[$key] = [$group->g_title, $group->linkEdit, $group->linkDelete];

            if (! \in_array($group->g_id, $notForNew, true)) {
                $groupsNew[$key] = $group->g_title;
            }
            if (
                ! \in_array($group->g_id, $notForDefault, true)
                && 0 == $group->g_moderator
            ) {
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
     */
    public function view(): Page
    {
        $this->nameTpl     = 'admin/groups';
        $this->formNew     = $this->formNew();
        $this->formDefault = $this->formDefault();

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formNew(): array
    {
        return [
            'action' => $this->c->Router->link('AdminGroupsNew'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminGroupsNew'),
            ],
            'sets'   => [
                'base' => [
                    'legend' => __('Add group subhead'),
                    'fields' => [
                        'basegroup' => [
                            'type'      => 'select',
                            'options'   => $this->groupsNew,
                            'value'     => $this->c->config->i_default_user_group,
                            'caption'   => 'New group label',
                            'help'      => 'New group help',
#                           'autofocus' => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('Add'),
                ],
            ],
        ];
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formDefault(): array
    {
        return [
            'action' => $this->c->Router->link('AdminGroupsDefault'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminGroupsDefault'),
            ],
            'sets'   => [
                'del' => [
                    'legend' => __('Default group subhead'),
                    'fields' => [
                        'defaultgroup' => [
                            'type'    => 'select',
                            'options' => $this->groupsDefault,
                            'value'   => $this->c->config->i_default_user_group,
                            'caption' => 'Default group label',
                            'help'    => 'Default group help',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit'  => [
                    'type'  => 'submit',
                    'value' => __('Update'),
                ],
            ],
        ];
    }

    /**
     * Устанавливает группу по умолчанию
     */
    public function defaultSet(): Page
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
        $this->c->config->i_default_user_group = $v->defaultgroup;
        $this->c->config->save();

        return $this->c->Redirect->page('AdminGroups')->message('Default group redirect');
    }

    /**
     * Редактирование группы
     * Создание новой группы
     */
    public function edit(array $args, string $method): Page
    {
        // начало создания новой группы
        if (
            empty($args['id'])
            && empty($args['base'])
        ) {
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
            $gid  = $args['id'] ?? $args['base'];
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
            $this->aCrumbs[] = [
                $this->c->Router->link($marker, $vars),
                __('Edit group'),
            ];
            $this->aCrumbs[] = __(['"%s"', $group->g_title]);
            $this->titleForm = 'Edit group';
            $this->classForm = 'editgroup';
        } else {
            $marker          = 'AdminGroupsNew';
            $vars            = ['base' => $group->g_id];
            $group->g_title  = '';
            $group->g_id     = null;
            $this->aCrumbs[] = __('Create new group');
            $this->titleForm = 'Create new group';
            $this->classForm = 'creategroup';
        }

        if (
            'POST' === $method
            && $next
        ) {
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
                    'g_read_board'           => 'required|integer|in:0,1',
                    'g_view_users'           => 'required|integer|in:0,1',
                    'g_post_replies'         => 'required|integer|in:0,1',
                    'g_post_topics'          => 'required|integer|in:0,1',
                    'g_post_links'           => 'required|integer|in:0,1',
                    'g_search'               => 'required|integer|in:0,1',
                    'g_search_users'         => 'required|integer|in:0,1',
                    'g_post_flood'           => 'required|integer|min:0|max:999999',
                    'g_search_flood'         => 'required|integer|min:0|max:999999',
                ]);

                if (
                    ! $group->groupGuest
                    && ! $group->groupMember
                    && $group->g_id !== $this->c->config->i_default_user_group
                ) {
                    $v->addRules([
                        'g_moderator'            => 'required|integer|in:0,1',
                        'g_mod_edit_users'       => 'required|integer|in:0,1',
                        'g_mod_rename_users'     => 'required|integer|in:0,1',
                        'g_mod_change_passwords' => 'required|integer|in:0,1',
                        'g_mod_promote_users'    => 'required|integer|in:0,1',
                        'g_mod_ban_users'        => 'required|integer|in:0,1',
                    ]);
                }

                if (! $group->groupGuest) {
                    $v->addRules([
                        'g_promote_next_group'   => 'required|integer|min:0|not_in:' . $notNext,
                        'g_promote_min_posts'    => 'required|integer|min:0|max:9999999999',
                        'g_edit_posts'           => 'required|integer|in:0,1',
                        'g_delete_posts'         => 'required|integer|in:0,1',
                        'g_delete_topics'        => 'required|integer|in:0,1',
                        'g_deledit_interval'     => 'required|integer|min:0|max:999999',
                        'g_set_title'            => 'required|integer|in:0,1',
                        'g_send_email'           => 'required|integer|in:0,1',
                        'g_email_flood'          => 'required|integer|min:0|max:999999',
                        'g_report_flood'         => 'required|integer|min:0|max:999999',
                        'g_sig_length'           => 'required|integer|min:0|max:10000',
                        'g_sig_lines'            => 'required|integer|min:0|max:255',
                        'g_pm'                   => 'required|integer|in:0,1',
                        'g_pm_limit'             => 'required|integer|min:0|max:999999',
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
        $this->form    = $this->formEdit($vars, $group, $marker);

        return $this;
    }

    /**
     * Запись данных по новой/измененной группе
     */
    public function save(Group $group, Group $baseGroup, array $data): Page
    {
        if (
            ! $group->groupAdmin
            && isset($data['g_moderator'])
            && 0 === $data['g_moderator']
        ) {
            $data['g_mod_edit_users']       = 0;
            $data['g_mod_rename_users']     = 0;
            $data['g_mod_change_passwords'] = 0;
            $data['g_mod_promote_users']    = 0;
            $data['g_mod_ban_users']        = 0;
        }
        if (
            isset($data['g_promote_next_group'])
            && 0 == $data['g_promote_next_group'] * $data['g_promote_min_posts']
        ) {
            $data['g_promote_next_group'] = 0;
            $data['g_promote_min_posts']  = 0;
        }

        foreach ($data as $attr => $value) {
            $group->$attr = $value;
        }

        if (null === $group->g_id) {
            $message = 'Group added redirect';
            $this->c->groups->insert($group);

            $this->c->groups->Perm->copy($baseGroup, $group);
        } else {
            $message = 'Group edited redirect';
            $this->c->groups->update($group);

            if ($group->g_promote_min_posts) {
                $this->c->users->promote($group);
            }
        }

        $this->c->forums->reset();

        return $this->c->Redirect->page('AdminGroupsEdit', ['id' => $group->g_id])->message($message);
    }

    /**
     * Формирует данные для формы редактирования группы
     */
    protected function formEdit(array $args, Group $group, string $marker): array
    {
        $form = [
            'action' => $this->c->Router->link($marker, $args),
            'hidden' => [
                'token' => $this->c->Csrf->create($marker, $args),
            ],
            'sets'   => [],
            'btns'   => [
                'submit'  => [
                    'type'  => 'submit',
                    'value' => null === $group->g_id ? __('Add') : __('Update'),
                ],
            ],
        ];

        $fieldset = [];
        $fieldset['g_title'] = [
            'type'      => 'text',
            'maxlength' => '50',
            'value'     => $group->g_title,
            'caption'   => 'Group title label',
            'required'  => true,
#           'autofocus' => true,
        ];
        $fieldset['g_user_title'] = [
            'type'      => 'text',
            'maxlength' => '50',
            'value'     => $group->g_user_title,
            'caption'   => 'User title label',
            'help'      => ['User title help', $group->groupGuest ? __('Guest') : __('Member')],
        ];
        $form['sets']['group-titles'] = [
            'legend' => __('Titles subhead'),
            'fields' => $fieldset,
        ];

        if ($group->groupAdmin) {
            return $form;
        }

        if (! $group->groupGuest) {
            $fieldset = [];
            $options  = [0 => __('Disable promotion')];

            foreach ($this->groupsNew as $key => $title) {
                if (
                    $key !== $group->g_id
                    && $key !== $this->c->GROUP_GUEST
                ) {
                    $options[$key] = $title;
                }
            }

            $fieldset['g_promote_next_group'] = [
                'type'    => 'select',
                'options' => $options,
                'value'   => $group->g_promote_next_group,
                'caption' => 'Promote users label',
                'help'    => ['Promote users help', __('Disable promotion')],
            ];
            $fieldset['g_promote_min_posts'] = [
                'type'    => 'number',
                'min'     => '0',
                'max'     => '9999999999',
                'value'   => $group->g_promote_min_posts,
                'caption' => 'Number for promotion label',
                'help'    => 'Number for promotion help',
            ];
            $form['sets']['group-promote'] = [
                'legend' => __('Promotion subhead'),
                'fields' => $fieldset,
            ];
        }


        $yn = [1 => __('Yes'), 0 => __('No')];

        if (
            ! $group->groupGuest
            && ! $group->groupMember
            && $group->g_id !== $this->c->config->i_default_user_group
        ) {
            $fieldset = [];
            $fieldset['g_moderator'] = [
                'type'    => 'radio',
                'value'   => $group->g_moderator,
                'values'  => $yn,
                'caption' => 'Mod privileges label',
                'help'    => 'Mod privileges help',
            ];
            $fieldset['g_mod_edit_users'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_edit_users,
                'values'  => $yn,
                'caption' => 'Edit profile label',
                'help'    => 'Edit profile help',
            ];
            $fieldset['g_mod_rename_users'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_rename_users,
                'values'  => $yn,
                'caption' => 'Rename users label',
                'help'    => 'Rename users help',
            ];
            $fieldset['g_mod_change_passwords'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_change_passwords,
                'values'  => $yn,
                'caption' => 'Change passwords label',
                'help'    => 'Change passwords help',
            ];
            $fieldset['g_mod_promote_users'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_promote_users,
                'values'  => $yn,
                'caption' => 'Mod promote users label',
                'help'    => 'Mod promote users help',
            ];
            $fieldset['g_mod_ban_users'] = [
                'type'    => 'radio',
                'value'   => $group->g_mod_ban_users,
                'values'  => $yn,
                'caption' => 'Ban users label',
                'help'    => 'Ban users help',
            ];
            $form['sets']['group-mod'] = [
                'legend' => __('Moderation subhead'),
                'fields' => $fieldset,
            ];
            $form['sets']['mod-info'] = [
                'info' => [
                    [
                        'value' => __('Moderator info'),
                    ],
                ],
            ];
        }

        $fieldset = [];
        $fieldset['g_read_board'] = [
            'type'    => 'radio',
            'value'   => $group->g_read_board,
            'values'  => $yn,
            'caption' => 'Read board label',
            'help'    => 'Read board help',
        ];
        $fieldset['g_view_users'] = [
            'type'    => 'radio',
            'value'   => $group->g_view_users,
            'values'  => $yn,
            'caption' => 'View user info label',
            'help'    => 'View user info help',
        ];
        $fieldset['g_post_replies'] = [
            'type'    => 'radio',
            'value'   => $group->g_post_replies,
            'values'  => $yn,
            'caption' => 'Post replies label',
            'help'    => 'Post replies help',
        ];
        $fieldset['g_post_topics'] = [
            'type'    => 'radio',
            'value'   => $group->g_post_topics,
            'values'  => $yn,
            'caption' => 'Post topics label',
            'help'    => 'Post topics help',
        ];

        if (! $group->groupGuest) {
            $fieldset['g_edit_posts'] = [
                'type'    => 'radio',
                'value'   => $group->g_edit_posts,
                'values'  => $yn,
                'caption' => 'Edit posts label',
                'help'    => 'Edit posts help',
            ];
            $fieldset['g_delete_posts'] = [
                'type'    => 'radio',
                'value'   => $group->g_delete_posts,
                'values'  => $yn,
                'caption' => 'Delete posts label',
                'help'    => 'Delete posts help',
            ];
            $fieldset['g_delete_topics'] = [
                'type'    => 'radio',
                'value'   => $group->g_delete_topics,
                'values'  => $yn,
                'caption' => 'Delete topics label',
                'help'    => 'Delete topics help',
            ];
            $fieldset['g_set_title'] = [
                'type'    => 'radio',
                'value'   => $group->g_set_title,
                'values'  => $yn,
                'caption' => 'Set own title label',
                'help'    => 'Set own title help',
            ];
        }

        $fieldset['g_post_links'] = [
            'type'    => 'radio',
            'value'   => $group->g_post_links,
            'values'  => $yn,
            'caption' => 'Post links label',
            'help'    => 'Post links help',
        ];
        $fieldset['g_search'] = [
            'type'    => 'radio',
            'value'   => $group->g_search,
            'values'  => $yn,
            'caption' => 'User search label',
            'help'    => 'User search help',
        ];
        $fieldset['g_search_users'] = [
            'type'    => 'radio',
            'value'   => $group->g_search_users,
            'values'  => $yn,
            'caption' => 'User list search label',
            'help'    => 'User list search help',
        ];

        if (! $group->groupGuest) {
            $fieldset['g_send_email'] = [
                'type'    => 'radio',
                'value'   => $group->g_send_email,
                'values'  => $yn,
                'caption' => 'Send e-mails label',
                'help'    => 'Send e-mails help',
            ];
        }

        $form['sets']['group-permissions'] = [
            'legend' => __('Permissions subhead'),
            'fields' => $fieldset,
        ];
        $form['sets']['def-info'] = [
            'info' => [
                [
                    'value' => __('Group settings info'),
                ],
            ],
        ];

        $fieldset = [];
        $fieldset['g_post_flood'] = [
            'type'    => 'number',
            'min'     => '0',
            'max'     => '999999',
            'value'   => $group->g_post_flood,
            'caption' => 'Post flood label',
            'help'    => 'Post flood help',
        ];
        $fieldset['g_search_flood'] = [
            'type'    => 'number',
            'min'     => '0',
            'max'     => '999999',
            'value'   => $group->g_search_flood,
            'caption' => 'Search flood label',
            'help'    => 'Search flood help',
        ];

        if (! $group->groupGuest) {
            $fieldset['g_deledit_interval'] = [
                'type'    => 'number',
                'min'     => '0',
                'max'     => '999999',
                'value'   => $group->g_deledit_interval,
                'caption' => 'Delete-edit interval label',
                'help'    => 'Delete-edit interval help',
            ];
            $fieldset['g_email_flood'] = [
                'type'    => 'number',
                'min'     => '0',
                'max'     => '999999',
                'value'   => $group->g_email_flood,
                'caption' => 'E-mail flood label',
                'help'    => 'E-mail flood help',
            ];
            $fieldset['g_report_flood'] = [
                'type'    => 'number',
                'min'     => '0',
                'max'     => '999999',
                'value'   => $group->g_report_flood,
                'caption' => 'Report flood label',
                'help'    => 'Report flood help',
            ];

        }

        $form['sets']['group-intervals'] = [
            'legend' => __('Intervals subhead'),
            'fields' => $fieldset,
        ];

        if (! $group->groupGuest) {
            $fieldset = [];
            $fieldset['g_sig_length'] = [
                'type'    => 'number',
                'min'     => '0',
                'max'     => '16000',
                'value'   => $group->g_sig_length,
                'caption' => 'Max sig length label',
                'help'    => 'Max sig length help',
            ];
            $fieldset['g_sig_lines'] = [
                'type'    => 'number',
                'min'     => '0',
                'max'     => '100',
                'value'   => $group->g_sig_lines,
                'caption' => 'Max sig lines label',
                'help'    => 'Max sig lines help',
            ];
            $form['sets']['group-signature'] = [
                'legend' => __('Signature subhead'),
                'fields' => $fieldset,
            ];


            $fieldset = [];
            $fieldset['g_pm'] = [
                'type'    => 'radio',
                'value'   => $group->g_pm,
                'values'  => $yn,
                'caption' => 'Allow PM label',
            ];
            $fieldset['g_pm_limit'] = [
                'type'    => 'number',
                'min'     => '0',
                'max'     => '999999',
                'value'   => $group->g_pm_limit,
                'caption' => 'PM limit label',
                'help'    => 'PM limit help',
            ];
            $form['sets']['group-pm'] = [
                'legend' => __('PM subhead'),
                'fields' => $fieldset,
            ];
        }

        return $form;
    }

    /**
     * Удаление группы
     */
    public function delete(array $args, string $method): Page
    {
        $group = $this->c->groups->get($args['id']);

        if (
            null === $group
            || ! $group->canDelete
        ) {
            return $this->c->Message->message('Bad request');
        }

        $count  = $this->c->users->usersNumber($group);
        $groups = [];
        if ($count) {
            $move = 'required|integer|in:';
            foreach ($this->groupsList as $key => $cur) {
                if (
                    $key === $this->c->GROUP_GUEST
                    || $key === $group->g_id
                ) {
                    continue;
                }
                $groups[$key] = $cur[0];
            }
            $move .= \implode(',', \array_keys($groups));
        } else {
            $move = 'absent';
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:AdminGroupsDelete',
                    'movegroup' => $move,
                    'confirm'   => 'checkbox',
                    'delete'    => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (
                ! $v->validation($_POST)
                || '1' !== $v->confirm
            ) {
                return $this->c->Redirect->page('AdminGroups')->message('No confirm redirect');
            }

            if ($v->movegroup) {
                $this->c->groups->delete($group, $this->c->groups->get($v->movegroup));
            } else {
                $this->c->groups->delete($group);
            }

            return $this->c->Redirect->page('AdminGroups')->message('Group removed redirect');
        }


        $this->nameTpl   = 'admin/form';
        $this->aCrumbs[] = [
            $this->c->Router->link('AdminGroupsDelete', $args),
            __('Group delete'),
        ];
        $this->aCrumbs[] = __(['"%s"', $group->g_title]);
        $this->form      = $this->formDelete($args, $group, $count, $groups);
        $this->titleForm = 'Group delete';
        $this->classForm = 'deletegroup';

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formDelete(array $args, Group $group, int $count, array $groups): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminGroupsDelete', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminGroupsDelete', $args),
            ],
            'sets'   => [],
            'btns'   => [
                'delete' => [
                    'type'  => 'submit',
                    'value' => __('Delete group'),
                ],
                'cancel' => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'link'  => $this->c->Router->link('AdminGroups'),
                ],
            ],
        ];

        if ($count) {
            $form['sets']['move'] = [
                'fields' => [
                    'movegroup' => [
                        'type'    => 'select',
                        'options' => $groups,
                        'value'   => $this->c->config->i_default_user_group,
                        'caption' => 'Move users label',
                        'help'    => ['Move users info', $group->g_title, $count],
                    ],
                ],
            ];
        }

        $form['sets']['conf'] = [
            'fields' => [
                'confirm' => [
                    'caption' => 'Confirm delete',
                    'type'    => 'checkbox',
                    'label'   => __(['I want to delete this group', $group->g_title]),
                    'value'   => '1',
                    'checked' => false,
                ],
            ],
        ];
        $form['sets']['conf-info'] = [
            'info' => [
                [
                    'value' => __('Confirm delete warn'),
                    'html'  => true,
                ],
            ],
        ];

        return $form;
    }
}
