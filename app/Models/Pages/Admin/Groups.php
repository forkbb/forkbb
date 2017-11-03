<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin;

class Groups extends Admin
{
    /**
     * Массив групп
     * @var array
     */
    protected $groups;

    /**
     * Список групп доступных как основа для новой
     * @var array
     */
    protected $grBase = [];

    /**
     * Список групп доступных для группы по умолчанию
     * @var array
     */
    protected $grDefault = [];

    /**
     * Список групп доступных для удаления
     * @var array
     */
    protected $grDelete = [];

    /**
     * Конструктор
     * 
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->getGroup();
        $forBase = [$this->c->GROUP_UNVERIFIED, $this->c->GROUP_ADMIN, $this->c->GROUP_GUEST];
        $forDelete = [$this->c->GROUP_UNVERIFIED, $this->c->GROUP_ADMIN, $this->c->GROUP_MOD, $this->c->GROUP_GUEST, $this->c->GROUP_MEMBER];

        foreach ($this->groups as $key => $cur) {
            if (! in_array($key, $forBase)) {
                $this->grBase[$key] = true;
                if ($cur['g_moderator'] == 0) {
                    $this->grDefault[$key] = true;
                }
                if (! in_array($key, $forDelete)) {
                    $this->grDelete[$key] = true;
                }
            }
        }
        $this->aIndex = 'groups';
    }

    /**
     * Создает массив групп
     */
    protected function getGroup()
    {
        if (empty($this->groups)) {
            $this->groups = [];
            $stmt = $this->c->DB->query('SELECT * FROM ::groups ORDER BY g_id');
            while ($cur = $stmt->fetch()) {
                $this->groups[$cur['g_id']] = $cur;
            }
        }
    }

    /**
     * Подготавливает данные для шаблона
     * 
     * @return Page
     */
    public function view()
    {
        $groupsList = [];
        $groupsNew = [];
        $groupsDefault = [];
        foreach ($this->groups as $key => $cur) {
            $groupsList[] = [
                $this->c->Router->link('AdminGroupsEdit', ['id' => $key]),
                $cur['g_title'],
                isset($this->grDelete[$key]) 
                    ? $this->c->Router->link('AdminGroupsDelete', ['id' => $key])
                    : null,
            ];
            if (isset($this->grBase[$key])) {
                $groupsNew[] = [$key, $cur['g_title']];
            }
            if (isset($this->grDefault[$key])) {
                $groupsDefault[] = [$key, $cur['g_title']];
            }
        }

        $this->c->Lang->load('admin_groups');

        $this->nameTpl = 'admin/groups';
        $this->formActionNew     = $this->c->Router->link('AdminGroupsNew');
        $this->formTokenNew      = $this->c->Csrf->create('AdminGroupsNew');
        $this->formActionDefault = $this->c->Router->link('AdminGroupsDefault');
        $this->formTokenDefault  = $this->c->Csrf->create('AdminGroupsDefault');
        $this->defaultGroup      = $this->c->config->o_default_user_group;
        $this->groupsNew         = $groupsNew;
        $this->groupsDefault     = $groupsDefault;
        $this->groupsList        = $groupsList;
        $this->tabindex          = 0;

        return $this;
    }

    /**
     * Устанавливает группу по умолчанию
     * 
     * @return Page
     */
    public function defaultPost()
    {
        $this->c->Lang->load('admin_groups');

        $v = $this->c->Validator->setRules([
            'token'        => 'token:AdminGroupsDefault',
            'defaultgroup' => 'required|integer|in:' . implode(',', array_keys($this->grDefault)),
        ]);

        if (! $v->validation($_POST)) {
            $this->fIswev = $v->getErrors();
            return $this->view();
        }
        $this->c->config->o_default_user_group = $v->defaultgroup;
        $this->c->config->save();
        return $this->c->Redirect->page('AdminGroups')->message(__('Default group redirect'));
    }

    /**
     * Подготавливает данные для создание группы
     * Создает новую группу
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function newPost(array $args)
    {
        $this->c->Lang->load('admin_groups');

        if (empty($args['base'])) {
            $v = $this->c->Validator->setRules([
                'token'     => 'token:AdminGroupsNew',
                'basegroup' => ['required|integer|in:' . implode(',', array_keys($this->grBase)), __('New group label')]
            ]);

            if (! $v->validation($_POST)) {
                $this->fIswev = $v->getErrors();
                return $this->view();
            } else {
                return $this->edit(['id' => $v->basegroup, '_new' => true]);
            }
        } else {
            return $this->editPost(['id' => $args['base'], '_new' => true]);
        }
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
        $groups = $this->groups;

        if (isset($args['_data'])) {
            $groups[$args['id']] = $args['_data'];
        } elseif (! isset($groups[$args['id']])) {
            return $this->c->Message->message('Bad request');
        }

        if (isset($args['_new'])) {
            $id = -1;
            $marker = 'AdminGroupsNew';
            $vars = ['base' => $args['id']];
            if (! isset($args['_data'])) {
                unset($groups[$args['id']]['g_title']);
            }
        } else {
            $id = (int) $args['id'];
            $marker = 'AdminGroupsEdit';
            $vars = ['id' => $id];
        }

        $this->c->Lang->load('admin_groups');

        $this->formAction = $this->c->Router->link($marker, $vars);
        $this->formToken  = $this->c->Csrf->create($marker, $vars);
        $this->form       = $this->viewForm($id, $groups[$args['id']]);
        $this->warn       = empty($groups[$args['id']]['g_moderator']) ? null : __('Moderator info');
        $this->tabindex   = 0;

        return $this;
    }

    /**
     * Запись данных по новой/измененной группе
     * 
     * @param array $args
     * 
     * @return Page
     */
    public function editPost(array $args)
    {
        $next = $this->c->GROUP_ADMIN . ',' . $this->c->GROUP_GUEST;
        if (isset($args['_new'])) {
            $id = -1;
            $marker = 'AdminGroupsNew';
            $vars = ['base' => $args['id']];
        } else {
            $id = (int) $args['id'];
            $marker = 'AdminGroupsEdit';
            $vars = ['id' => $id];
            $next .= ',' . $id;
        }
        $reserve = [];
        foreach ($this->groups as $key => $cur) {
            if ($key != $id) {
                $reserve[] = $cur['g_title'];
            }
        }

        $this->c->Lang->load('admin_groups');

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
            'token' => $vars,
        ]);

        if (! $v->validation($_POST)) {
            $this->fIswev = $v->getErrors();
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

        $fields = [];
        $sets = [];
        $vars = [];
        foreach($data as $key => $value) {
            if (substr($key, 0, 2) !== 'g_' || $value === null) {
                continue;
            } elseif ($key === 'g_user_title' && ! isset($value{0})) {
                $value = null;
            }
            if ($id === -1) {
                $fields[] = $key;
                $sets[] = is_int($value) ? '?i' : '?s';
                $vars[] = $value;
            } else {
                if ($id === $this->c->GROUP_ADMIN 
                    && ! in_array($key, ['g_title', 'g_user_title'])
                ) {
                    continue;
                }
                $sets[] = $key . '=' . (is_int($value) ? '?i' : '?s');
                $vars[] = $value;
            } 
        }
        if ($id === -1) {
            $this->c->DB->exec('INSERT INTO ::groups (' . implode(', ', $fields) . ') VALUES(' . implode(', ', $sets) . ')', $vars);
            $newId = $this->c->DB->lastInsertId();

            $this->c->DB->exec('INSERT INTO ::forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) SELECT ?i:new, forum_id, read_forum, post_replies, post_topics FROM ::forum_perms WHERE group_id=?i:old', [':new' => $newId, ':old' => $args['id']]);
        } else {
            $vars[] = $id;
            $this->c->DB->exec('UPDATE ::groups SET ' . implode(', ', $sets) . ' WHERE g_id=?i', $vars);

            if ($data['g_promote_next_group']) {
                $vars = [':next' => $data['g_promote_next_group'], ':id' => $id, ':posts' => $data['g_promote_min_posts']];
                $this->c->DB->exec('UPDATE ::users SET group_id=?i:next WHERE group_id=?i:id AND num_posts>=?i:posts', $vars);
            }
        }

        $this->c->Cache->delete('forums_mark');

        return $this->c->Redirect
            ->page('AdminGroups')
            ->message($id === -1 ? __('Group added redirect') : __('Group edited redirect'));
    }

    /**
     * Формирует данные для формы редактирования группы
     * @param int $id
     * @param array $data
     * @return array
     */
    protected function viewForm($id, array $data)
    {
        $this->nameTpl = 'admin/group';
        $form = [
            'g_title' => [
                'type' => 'text',
                'maxlength' => 50,
                'value' => isset($data['g_title']) ? $data['g_title'] : '',
                'title' => __('Group title label'),
                'required' => true,
            ],
            'g_user_title' => [
                'type' => 'text',
                'maxlength' => 50,
                'value' => isset($data['g_user_title']) ? $data['g_user_title'] : '',
                'title' => __('User title label'),
                'info' => __('User title help', $id == $this->c->GROUP_GUEST ? __('Guest') : __('Member')),
            ],
        ];

        if ($id === $this->c->GROUP_UNVERIFIED || $id === $this->c->GROUP_ADMIN) {
            return $form;
        }

        if ($id !== $this->c->GROUP_GUEST) {
            $options = [0 => __('Disable promotion')];

            foreach ($this->groups as $group) {
                if ($group['g_id'] == $id || empty($this->grBase[$group['g_id']])) {
                    continue;
                }
                $options[$group['g_id']] = $group['g_title'];
            }

            $form['g_promote_next_group'] = [
                'type' => 'select',
                'options' => $options,
                'value' => isset($data['g_promote_next_group']) ? $data['g_promote_next_group'] : 0,
                'title' => __('Promote users label'),
                'info' => __('Promote users help', __('Disable promotion')),
            ];
            $form['g_promote_min_posts'] = [
                'type' => 'number',
                'min' => 0,
                'max' => 9999999999,
                'value' => isset($data['g_promote_min_posts']) ? $data['g_promote_min_posts'] : 0,
                'title' => __('Number for promotion label'),
                'info' => __('Number for promotion help'),
            ];
        }

        $y = __('Yes');
        $n = __('No');
        if ($id !== $this->c->GROUP_GUEST && $id != $this->c->config->o_default_user_group) {
            $form['g_moderator'] = [
                'type' => 'radio',
                'value' => isset($data['g_moderator']) ? $data['g_moderator'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Mod privileges label'),
                'info' => __('Mod privileges help'),
            ];
            $form['g_mod_edit_users'] = [
                'type' => 'radio',
                'value' => isset($data['g_mod_edit_users']) ? $data['g_mod_edit_users'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Edit profile label'),
                'info' => __('Edit profile help'),
            ];
            $form['g_mod_rename_users'] = [
                'type' => 'radio',
                'value' => isset($data['g_mod_rename_users']) ? $data['g_mod_rename_users'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Rename users label'),
                'info' => __('Rename users help'),
            ];
            $form['g_mod_change_passwords'] = [
                'type' => 'radio',
                'value' => isset($data['g_mod_change_passwords']) ? $data['g_mod_change_passwords'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Change passwords label'),
                'info' => __('Change passwords help'),
            ];
            $form['g_mod_promote_users'] = [
                'type' => 'radio',
                'value' => isset($data['g_mod_promote_users']) ? $data['g_mod_promote_users'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Mod promote users label'),
                'info' => __('Mod promote users help'),
            ];
            $form['g_mod_ban_users'] = [
                'type' => 'radio',
                'value' => isset($data['g_mod_ban_users']) ? $data['g_mod_ban_users'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Ban users label'),
                'info' => __('Ban users help'),
            ];
        }

        $form['g_read_board'] = [
            'type' => 'radio',
            'value' => isset($data['g_read_board']) ? $data['g_read_board'] : 0,
            'values' => [1 => $y, 0 => $n],
            'title' => __('Read board label'),
            'info' => __('Read board help'),
        ];
        $form['g_view_users'] = [
            'type' => 'radio',
            'value' => isset($data['g_view_users']) ? $data['g_view_users'] : 0,
            'values' => [1 => $y, 0 => $n],
            'title' => __('View user info label'),
            'info' => __('View user info help'),
        ];
        $form['g_post_replies'] = [
            'type' => 'radio',
            'value' => isset($data['g_post_replies']) ? $data['g_post_replies'] : 0,
            'values' => [1 => $y, 0 => $n],
            'title' => __('Post replies label'),
            'info' => __('Post replies help'),
        ];
        $form['g_post_topics'] = [
            'type' => 'radio',
            'value' => isset($data['g_post_topics']) ? $data['g_post_topics'] : 0,
            'values' => [1 => $y, 0 => $n],
            'title' => __('Post topics label'),
            'info' => __('Post topics help'),
        ];

        if ($id !== $this->c->GROUP_GUEST) {
            $form['g_edit_posts'] = [
                'type' => 'radio',
                'value' => isset($data['g_edit_posts']) ? $data['g_edit_posts'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Edit posts label'),
                'info' => __('Edit posts help'),
            ];
            $form['g_delete_posts'] = [
                'type' => 'radio',
                'value' => isset($data['g_delete_posts']) ? $data['g_delete_posts'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Delete posts label'),
                'info' => __('Delete posts help'),
            ];
            $form['g_delete_topics'] = [
                'type' => 'radio',
                'value' => isset($data['g_delete_topics']) ? $data['g_delete_topics'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Delete topics label'),
                'info' => __('Delete topics help'),
            ];
            $form['g_set_title'] = [
                'type' => 'radio',
                'value' => isset($data['g_set_title']) ? $data['g_set_title'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Set own title label'),
                'info' => __('Set own title help'),
            ];
        }

        $form['g_post_links'] = [
            'type' => 'radio',
            'value' => isset($data['g_post_links']) ? $data['g_post_links'] : 0,
            'values' => [1 => $y, 0 => $n],
            'title' => __('Post links label'),
            'info' => __('Post links help'),
        ];
        $form['g_search'] = [
            'type' => 'radio',
            'value' => isset($data['g_search']) ? $data['g_search'] : 0,
            'values' => [1 => $y, 0 => $n],
            'title' => __('User search label'),
            'info' => __('User search help'),
        ];
        $form['g_search_users'] = [
            'type' => 'radio',
            'value' => isset($data['g_search_users']) ? $data['g_search_users'] : 0,
            'values' => [1 => $y, 0 => $n],
            'title' => __('User list search label'),
            'info' => __('User list search help'),
        ];

        if ($id !== $this->c->GROUP_GUEST) {
            $form['g_send_email'] = [
                'type' => 'radio',
                'value' => isset($data['g_send_email']) ? $data['g_send_email'] : 0,
                'values' => [1 => $y, 0 => $n],
                'title' => __('Send e-mails label'),
                'info' => __('Send e-mails help'),
            ];
        }

        $form['g_post_flood'] = [
            'type' => 'number',
            'min' => 0,
            'max' => 999999,
            'value' => isset($data['g_post_flood']) ? $data['g_post_flood'] : 0,
            'title' => __('Post flood label'),
            'info' => __('Post flood help'),
        ];
        $form['g_search_flood'] = [
            'type' => 'number',
            'min' => 0,
            'max' => 999999,
            'value' => isset($data['g_search_flood']) ? $data['g_search_flood'] : 0,
            'title' => __('Search flood label'),
            'info' => __('Search flood help'),
        ];

        if ($id !== $this->c->GROUP_GUEST) {
            $form['g_email_flood'] = [
                'type' => 'number',
                'min' => 0,
                'max' => 999999,
                'value' => isset($data['g_email_flood']) ? $data['g_email_flood'] : 0,
                'title' => __('E-mail flood label'),
                'info' => __('E-mail flood help'),
            ];
            $form['g_report_flood'] = [
                'type' => 'number',
                'min' => 0,
                'max' => 999999,
                'value' => isset($data['g_report_flood']) ? $data['g_report_flood'] : 0,
                'title' => __('Report flood label'),
                'info' => __('Report flood help'),
            ];
        }

        return $form;
    }
}
