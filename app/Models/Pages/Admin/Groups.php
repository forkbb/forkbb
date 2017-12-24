<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;
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

            if (! in_array($group->g_id, $notForNew)) {
                $groupsNew[$key] = $group->g_title;
            }
            if (! in_array($group->g_id, $notForDefault) && $group->g_moderator == 0) {
                $groupsDefault[$key] = $group->g_title;
            }
        }
        $this->groupsList    = $groupsList;
        $this->groupsNew     = $groupsNew;
        $this->groupsDefault = $groupsDefault;
        $this->aIndex        = 'groups';
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
            'sets'   => [[
                'fields' => [
                    'basegroup' => [
                        'type'      => 'select',
                        'options'   => $this->groupsNew,
                        'value'     => $this->c->config->o_default_user_group,
                        'title'     => \ForkBB\__('New group label'),
                        'info'      => \ForkBB\__('New group help'),
                        'autofocus' => true,
                    ],
                ],
            ]],
            'btns'   => [
                'submit'  => [
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
            'sets'   => [[
                'fields' => [
                    'defaultgroup' => [
                        'type'    => 'select',
                        'options' => $this->groupsDefault,
                        'value'   => $this->c->config->o_default_user_group,
                        'title'   => \ForkBB\__('Default group label'),
                        'info'    => \ForkBB\__('Default group help'),
                    ],
                ],
            ]],
            'btns'   => [
                'submit'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Save'),
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
    public function defaultPost()
    {
        $v = $this->c->Validator->setRules([
            'token'        => 'token:AdminGroupsDefault',
            'defaultgroup' => 'required|integer|in:' . implode(',', array_keys($this->groupsDefault)),
        ])->setMessages([
            'defaultgroup.in' => 'Invalid default group',
        ]);

        if (! $v->validation($_POST)) {
            $this->fIswev = $v->getErrors();
            return $this->view();
        }
        $this->c->config->o_default_user_group = $v->defaultgroup;
        $this->c->config->save();
        return $this->c->Redirect->page('AdminGroups')->message(\ForkBB\__('Default group redirect'));
    }

    /**
     * Подготавливает данные для шаблона редактирования группы
     *
     * @param array $args
     *
     * @return Page
     */
    public function edit(array $args)
    {
        if (isset($args['base'])) {
            $group = $this->c->groups->get((int) $args['base']);
        } else {
            $group = $this->c->groups->get((int) $args['id']);
        }

        if (null === $group) {
            return $this->c->Message->message('Bad request');
        }

        $group = clone $group;

        if (isset($args['base'])) {
            $vars            = ['base' => $group->g_id];
            $group->g_title  = '';
            $group->g_id     = null;
            $marker          = 'AdminGroupsNew';
            $this->titles    = \ForkBB\__('Create new group');
            $this->titleForm = \ForkBB\__('Create new group');
        } else {
            $vars            = ['id' => $group->g_id];
            $marker          = 'AdminGroupsEdit';
            $this->titles    = \ForkBB\__('Edit group');
            $this->titleForm = \ForkBB\__('Edit group');
        }

        if (isset($args['_data'])) {
            $group->replAttrs($args['_data']);
        }

        $this->nameTpl = 'admin/group';
        $this->form    = $this->viewForm($group, $marker, $vars);

        return $this;
    }

    /**
     * Создание новой группы
     * Запись данных по новой/измененной группе
     *
     * @param array $args
     *
     * @return Page
     */
    public function editPost(array $args)
    {
        // начало создания новой группы
        if (empty($args['id']) && empty($args['base'])) {
            $v = $this->c->Validator->setRules([
                'token'     => 'token:AdminGroupsNew',
                'basegroup' => 'required|integer|in:' . implode(',', array_keys($this->groupsNew)),
            ])->setMessages([
                'basegroup.in' => 'Invalid group to create on base',
            ]);

            if (! $v->validation($_POST)) {
                $this->fIswev = $v->getErrors();
                return $this->view();
            } else {
                return $this->edit(['base' => $v->basegroup]);
            }
        }

        if (isset($args['base'])) {
            $group = $this->c->groups->get((int) $args['base']);
        } else {
            $group = $this->c->groups->get((int) $args['id']);
        }

        if (null === $group) {
            return $this->c->Message->message('Bad request');
        }

        $group = clone $group;

        $next = $this->c->GROUP_ADMIN . ',' . $this->c->GROUP_GUEST;

        if (isset($args['base'])) {
            $marker      = 'AdminGroupsNew';
            $group->g_id = null;
        } else {
            $marker = 'AdminGroupsEdit';
            $next  .= ',' . $group->g_id;
        }

        $reserve = [];
        foreach ($this->groupsList as $key => $cur) {
            if ($group->g_id !== $key) {
                $reserve[] = $cur[0];
            }
        }

        $v = $this->c->Validator->setRules([
            'token'                  => 'token:' . $marker,
            'g_title'                => 'required|string:trim|max:50|not_in:' . implode(',', $reserve),
            'g_user_title'           => 'string:trim|max:50',
            'g_promote_next_group'   => 'integer|min:0|not_in:' . $next,
            'g_promote_min_posts'    => 'integer|min:0|max:9999999999',
            'g_moderator'            => 'integer|in:0,1',
            'g_mod_edit_users'       => 'integer|in:0,1',
            'g_mod_rename_users'     => 'integer|in:0,1',
            'g_mod_change_passwords' => 'integer|in:0,1',
            'g_mod_promote_users'    => 'integer|in:0,1',
            'g_mod_ban_users'        => 'integer|in:0,1',
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
        ])->setArguments([
            'token' => $args,
        ])->setMessages([
            'g_title.required' => 'You must enter a group title',
            'g_title.not_in'   => 'Title already exists',
        ]);

        if (! $v->validation($_POST)) {
            $this->fIswev  = $v->getErrors();
            $args['_data'] = $v->getData();
            return $this->edit($args);
        }

        $data = $v->getData();

        if (empty($data['g_moderator'])) {
            $data['g_mod_edit_users']       = 0;
            $data['g_mod_rename_users']     = 0;
            $data['g_mod_change_passwords'] = 0;
            $data['g_mod_promote_users']    = 0;
            $data['g_mod_ban_users']        = 0;
        }
        if ($data['g_promote_next_group'] * $data['g_promote_min_posts'] == 0) {
            $data['g_promote_next_group'] = 0;
            $data['g_promote_min_posts']  = 0;
        }

        foreach ($data as $attr => $value) {
            $group->$attr = $value;
        }

        $this->c->DB->beginTransaction();

        if (null === $group->g_id) {
            $message = \ForkBB\__('Group added redirect');
            $newId   = $this->c->groups->insert($group);
            //????
            $this->c->DB->exec('INSERT INTO ::forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) SELECT ?i:new, forum_id, read_forum, post_replies, post_topics FROM ::forum_perms WHERE group_id=?i:old', [':new' => $newId, ':old' => $args['base']]);

        } else {
            $message = \ForkBB\__('Group edited redirect');
            $this->c->groups->update($group);
            //????
            if ($data['g_promote_next_group']) {
                $vars = [':next' => $data['g_promote_next_group'], ':id' => $group->g_id, ':posts' => $data['g_promote_min_posts']];
                $this->c->DB->exec('UPDATE ::users SET group_id=?i:next WHERE group_id=?i:id AND num_posts>=?i:posts', $vars);
            }
        }

        $this->c->DB->commit();

        $this->c->Cache->delete('forums_mark');

        return $this->c->Redirect->page('AdminGroups')->message($message);
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
                    'value'     => \ForkBB\__('Submit'),
                    'accesskey' => 's',
                ],
            ],
        ];

        $fieldset = [];
        $fieldset['g_title'] = [
            'type'      => 'text',
            'maxlength' => 50,
            'value'     => $group->g_title,
            'title'     => \ForkBB\__('Group title label'),
            'required'  => true,
            'autofocus' => true,
        ];
        $fieldset['g_user_title'] = [
            'type'      => 'text',
            'maxlength' => 50,
            'value'     => $group->g_user_title,
            'title'     => \ForkBB\__('User title label'),
            'info'      => \ForkBB\__('User title help', $group->groupGuest ? \ForkBB\__('Guest') : \ForkBB\__('Member')),
        ];

        if ($group->g_id === $this->c->GROUP_ADMIN) {
            $form['sets'][] = [
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
                'title'   => \ForkBB\__('Promote users label'),
                'info'    => \ForkBB\__('Promote users help', \ForkBB\__('Disable promotion')),
            ];
            $fieldset['g_promote_min_posts'] = [
                'type'  => 'number',
                'min'   => 0,
                'max'   => 9999999999,
                'value' => $group->g_promote_min_posts,
                'title' => \ForkBB\__('Number for promotion label'),
                'info'  => \ForkBB\__('Number for promotion help'),
            ];
        }

        $y = \ForkBB\__('Yes');
        $n = \ForkBB\__('No');

        if (! $group->groupGuest && $group->g_id != $this->c->config->o_default_user_group) {
            $fieldset['g_moderator'] = [
                'type'   => 'radio',
                'value'  => $group->g_moderator,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Mod privileges label'),
                'info'   => \ForkBB\__('Mod privileges help'),
            ];
            $fieldset['g_mod_edit_users'] = [
                'type'   => 'radio',
                'value'  => $group->g_mod_edit_users,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Edit profile label'),
                'info'   => \ForkBB\__('Edit profile help'),
            ];
            $fieldset['g_mod_rename_users'] = [
                'type'   => 'radio',
                'value'  => $group->g_mod_rename_users,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Rename users label'),
                'info'   => \ForkBB\__('Rename users help'),
            ];
            $fieldset['g_mod_change_passwords'] = [
                'type'   => 'radio',
                'value'  => $group->g_mod_change_passwords,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Change passwords label'),
                'info'   => \ForkBB\__('Change passwords help'),
            ];
            $fieldset['g_mod_promote_users'] = [
                'type'   => 'radio',
                'value'  => $group->g_mod_promote_users,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Mod promote users label'),
                'info'   => \ForkBB\__('Mod promote users help'),
            ];
            $fieldset['g_mod_ban_users'] = [
                'type'   => 'radio',
                'value'  => $group->g_mod_ban_users,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Ban users label'),
                'info'   => \ForkBB\__('Ban users help'),
            ];
        }

        $fieldset['g_read_board'] = [
            'type'   => 'radio',
            'value'  => $group->g_read_board,
            'values' => [1 => $y, 0 => $n],
            'title'  => \ForkBB\__('Read board label'),
            'info'   => \ForkBB\__('Read board help'),
        ];
        $fieldset['g_view_users'] = [
            'type'   => 'radio',
            'value'  => $group->g_view_users,
            'values' => [1 => $y, 0 => $n],
            'title'  => \ForkBB\__('View user info label'),
            'info'   => \ForkBB\__('View user info help'),
        ];
        $fieldset['g_post_replies'] = [
            'type'   => 'radio',
            'value'  => $group->g_post_replies,
            'values' => [1 => $y, 0 => $n],
            'title'  => \ForkBB\__('Post replies label'),
            'info'   => \ForkBB\__('Post replies help'),
        ];
        $fieldset['g_post_topics'] = [
            'type'   => 'radio',
            'value'  => $group->g_post_topics,
            'values' => [1 => $y, 0 => $n],
            'title'  => \ForkBB\__('Post topics label'),
            'info'   => \ForkBB\__('Post topics help'),
        ];

        if (! $group->groupGuest) {
            $fieldset['g_edit_posts'] = [
                'type'   => 'radio',
                'value'  => $group->g_edit_posts,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Edit posts label'),
                'info'   => \ForkBB\__('Edit posts help'),
            ];
            $fieldset['g_delete_posts'] = [
                'type'   => 'radio',
                'value'  => $group->g_delete_posts,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Delete posts label'),
                'info'   => \ForkBB\__('Delete posts help'),
            ];
            $fieldset['g_delete_topics'] = [
                'type'   => 'radio',
                'value'  => $group->g_delete_topics,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Delete topics label'),
                'info'   => \ForkBB\__('Delete topics help'),
            ];
            $fieldset['g_deledit_interval'] = [
                'type'  => 'number',
                'min'   => 0,
                'max'   => 999999,
                'value' => $group->g_deledit_interval,
                'title' => \ForkBB\__('Delete-edit interval label'),
                'info'  => \ForkBB\__('Delete-edit interval help'),
            ];
            $fieldset['g_set_title'] = [
                'type'   => 'radio',
                'value'  => $group->g_set_title,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Set own title label'),
                'info'   => \ForkBB\__('Set own title help'),
            ];
        }

        $fieldset['g_post_links'] = [
            'type'   => 'radio',
            'value'  => $group->g_post_links,
            'values' => [1 => $y, 0 => $n],
            'title'  => \ForkBB\__('Post links label'),
            'info'   => \ForkBB\__('Post links help'),
        ];
        $fieldset['g_search'] = [
            'type'   => 'radio',
            'value'  => $group->g_search,
            'values' => [1 => $y, 0 => $n],
            'title'  => \ForkBB\__('User search label'),
            'info'   => \ForkBB\__('User search help'),
        ];
        $fieldset['g_search_users'] = [
            'type'   => 'radio',
            'value'  => $group->g_search_users,
            'values' => [1 => $y, 0 => $n],
            'title'  => \ForkBB\__('User list search label'),
            'info'   => \ForkBB\__('User list search help'),
        ];

        if (! $group->groupGuest) {
            $fieldset['g_send_email'] = [
                'type'   => 'radio',
                'value'  => $group->g_send_email,
                'values' => [1 => $y, 0 => $n],
                'title'  => \ForkBB\__('Send e-mails label'),
                'info'   => \ForkBB\__('Send e-mails help'),
            ];
        }

        $fieldset['g_post_flood'] = [
            'type'  => 'number',
            'min'   => 0,
            'max'   => 999999,
            'value' => $group->g_post_flood,
            'title' => \ForkBB\__('Post flood label'),
            'info'  => \ForkBB\__('Post flood help'),
        ];
        $fieldset['g_search_flood'] = [
            'type'  => 'number',
            'min'   => 0,
            'max'   => 999999,
            'value' => $group->g_search_flood,
            'title' => \ForkBB\__('Search flood label'),
            'info'  => \ForkBB\__('Search flood help'),
        ];

        if (! $group->groupGuest) {
            $fieldset['g_email_flood'] = [
                'type'  => 'number',
                'min'   => 0,
                'max'   => 999999,
                'value' => $group->g_email_flood,
                'title' => \ForkBB\__('E-mail flood label'),
                'info'  => \ForkBB\__('E-mail flood help'),
            ];
            $fieldset['g_report_flood'] = [
                'type'  => 'number',
                'min'   => 0,
                'max'   => 999999,
                'value' => $group->g_report_flood,
                'title' => \ForkBB\__('Report flood label'),
                'info'  => \ForkBB\__('Report flood help'),
            ];
        }

        $form['sets'][] = [
            'fields' => $fieldset,
        ];

        if (! empty($group->g_moderator)) {
            $form['sets'][] = [
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
     * Подготавливает данные для шаблона
     *
     * @param array $args
     * 
     * @return Page
     */
    public function delete(array $args)
    {
        $group = $this->c->groups->get((int) $args['id']);

        if (null === $group || ! $group->canDelete) {
            return $this->c->Message->message('Bad request');
        }

        $form = [
            'action' => $this->c->Router->link('AdminGroupsDelete', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminGroupsDelete', $args),
            ],
            'sets'   => [],
            'btns'   => [
                'delete'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Delete group'),
                    'accesskey' => 'd',
                ],
            ],
        ];

        $form['sets'][] = [
            'info' => [
                'info1' => [
                    'type'  => '', //????
                    'value' => \ForkBB\__('Confirm delete warn'),
                ],
#                'info2' => [
#                    'type'  => '', //????
#                    'value' => \ForkBB\__('Confirm delete info', $group->g_title),
#                    'html'  => true,
#                ],
            ],
        ];
        $form['sets'][] = [
            'fields' => [
                'confirm' => [
#                    'dl'      => 'full',
                    'title'   => \ForkBB\__('Confirm delete'),
                    'type'    => 'checkbox',
                    'label'   => \ForkBB\__('I want to delete this group', $group->g_title),
                    'value'   => '1',
                    'checked' => false,
                ],
            ],
        ];

        $this->nameTpl = 'admin/group_delete';
        $this->titles  = \ForkBB\__('Group delete');
        $this->form    = $form;

        return $this;
    }
}
