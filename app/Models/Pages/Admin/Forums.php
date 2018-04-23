<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Pages\Admin;

class Forums extends Admin
{
    /**
     * Составление списка категорий/разделов для выбора родителя
     *
     * @param Forum $forum
     */
    protected function calcList(Forum $forum)
    {
        $cid        = null;
        $categories = $this->c->categories->getList();
        $options    = [
            ['', \ForkBB\__('Not selected')],
        ];
        $idxs       = [];
        $root = $this->c->forums->get(0);
        if ($root instanceof Forum) {
            foreach ($this->c->forums->depthList($root, 0) as $f) {
                if ($cid !== $f->cat_id) {
                    $cid       = $f->cat_id;
                    $options[] = [-$cid, \ForkBB\__('Category prefix') . $f->cat_name];
                    $idxs[]    = -$cid;
                    unset($categories[$cid]);
                }

                $indent = \str_repeat(\ForkBB\__('Forum indent'), $f->depth);

                if ($f->id === $forum->id || isset($forum->descendants[$f->id]) || $f->redirect_url) {
                    $options[] = [$f->id, $indent . \ForkBB\__('Forum prefix') . $f->forum_name, true];
                } else {
                    $options[] = [$f->id, $indent . \ForkBB\__('Forum prefix') . $f->forum_name];
                    $idxs[]    = $f->id;
                }
            }
        }
        foreach ($categories as $key => $row) {
            $idxs[]    = -$key;
            $options[] = [-$key, \ForkBB\__('Category prefix') . $row['cat_name']];
        }
        $this->listOfIndexes  = $idxs;
        $this->listForOptions = $options;
    }

    /**
     * Вычисление позиции для (нового) раздела
     *
     * @param Forum $forum
     *
     * @return int
     */
    protected function forumPos(Forum $forum)
    {
        if (\is_int($forum->disp_position)) {
            return $forum->disp_position;
        }

        $root = $this->c->forums->get(0);

        if (! $root instanceof Forum) {
            return 0;
        }

        $max = 0;
        foreach ($root->descendants as $f) {
            if ($f->disp_position > $max) {
                $max = $f->disp_position;
            }
        }
        return $max + 1;
    }

    /**
     * Просмотр, редактирвоание и добавление разделов
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
        $this->c->Lang->load('admin_forums');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'                => 'token:AdminForums',
                    'form.*.disp_position' => 'required|integer|min:0|max:9999999999',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $this->c->DB->beginTransaction();

                foreach ($v->form as $key => $row) {
                    $forum = $this->c->forums->get((int) $key);
                    $forum->disp_position = $row['disp_position'];
                    $this->c->forums->update($forum);
                }

                $this->c->DB->commit();

                $this->c->Cache->delete('forums_mark'); //????

                return $this->c->Redirect->page('AdminForums')->message('Forums updated redirect');
            }

            $this->fIswev  = $v->getErrors();
        }

        $form = [
            'action' => $this->c->Router->link('AdminForums'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminForums'),
            ],
            'sets'   => [],
            'btns'   => [
                'new' => [
                    'type'      => 'btn',
                    'value'     => \ForkBB\__('New forum'),
                    'link'      => $this->c->Router->link('AdminForumsNew'),
                    'accesskey' => 'n',
                ],
                'update' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Update positions'),
                    'accesskey' => 'u',
                ],
            ],
        ];

        $root = $this->c->forums->get(0);

        if ($root instanceof Forum) {
            $list = $this->c->forums->depthList($root, -1);

            $fieldset = [];
            $cid = null;
            foreach ($list as $forum) {
                if ($cid !== $forum->cat_id) {
                    if (null !== $cid) {
                        $form['sets']["cat{$cid}"] = [
                            'class'  => 'inline',
                            'fields' => $fieldset,
                        ];
                        $fieldset = [];
                    }

                    $form['sets']["cat{$forum->cat_id}-info"] = [
                        'info' => [
                            'info1' => [
                                'type'  => '', //????
                                'value' => $forum->cat_name,
                            ],
                        ],
                    ];
                    $cid = $forum->cat_id;
                }

                $fieldset["forum{$forum->id}"] = [
                    'class'   => ['name', 'adm-inline', 'depth' . $forum->depth],
                    'type'    => 'btn',
                    'value'   => $forum->forum_name,
                    'caption' => \ForkBB\__('Forum label'),
                    'link'    => $this->c->Router->link('AdminForumsEdit', ['id' => $forum->id]),
                ];
                $fieldset["form[{$forum->id}][disp_position]"] = [
                    'class'   => ['position', 'adm-inline'],
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 9999999999,
                    'value'   => $forum->disp_position,
                    'caption' => \ForkBB\__('Position label'),
                ];
                $disabled = (bool) $forum->subforums;
                $fieldset["forum{$forum->id}-del"] = [
                    'class'    => ['delete', 'adm-inline'],
                    'type'     => 'btn',
                    'value'    => '❌',
                    'caption'  => \ForkBB\__('Delete'),
                    'link'     => $disabled ? '#' : $this->c->Router->link('AdminForumsDelete', ['id' => $forum->id]),
                    'disabled' => $disabled,
                ];
            }

            $form['sets']["cat{$cid}"] = [
                'class'  => 'inline',
                'fields' => $fieldset,
            ];
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'forums';
        $this->form      = $form;
        $this->classForm = ['editforums', 'inline'];
        $this->titleForm = \ForkBB\__('Forums');

        return $this;
    }

    /**
     * Удаление раздела
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function delete(array $args, $method)
    {
        $forum = $this->c->forums->get((int) $args['id']);
        if (! $forum instanceof Forum || $forum->subforums) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('admin_forums');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:AdminForumsDelete',
                    'confirm'   => 'integer',
                    'delete'    => 'string',
                    'cancel'    => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (! $v->validation($_POST) || null === $v->delete) {
                return $this->c->Redirect->page('AdminForums')->message('Cancel redirect');
            } elseif ($v->confirm !== 1) {
                return $this->c->Redirect->page('AdminForums')->message('No confirm redirect');
            }

            $this->c->DB->beginTransaction();

            $this->c->forums->delete($forum);

            $this->c->DB->commit();

            $this->c->Cache->delete('forums_mark'); //????

            return $this->c->Redirect->page('AdminForums')->message('Forum deleted redirect');
        }

        $form = [
            'action' => $this->c->Router->link('AdminForumsDelete', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminForumsDelete', $args),
            ],
            'sets'   => [],
            'btns'   => [
                'delete' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Delete forum'),
                    'accesskey' => 'd',
                ],
                'cancel' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Cancel'),
                ],
            ],
        ];

        $form['sets'][] = [
            'fields' => [
                'confirm' => [
                    'caption' => \ForkBB\__('Confirm delete'),
                    'type'    => 'checkbox',
                    'label'   => \ForkBB\__('I want to delete forum %s', $forum->forum_name),
                    'value'   => '1',
                    'checked' => false,
                ],
            ],
        ];
        $form['sets'][] = [
            'info' => [
                'info1' => [
                    'type'  => '', //????
                    'value' => \ForkBB\__('Delete forum warn'),
                    'html'  => true,
                ],
            ],
        ];

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'forums';
        $this->aCrumbs[] = [$this->c->Router->link('AdminForumsDelete', ['id' => $forum->id]), \ForkBB\__('Delete forum head')];
        $this->aCrumbs[] = \ForkBB\__('"%s"', $forum->forum_name);
        $this->form      = $form;
        $this->classForm = ['deleteforum', 'btnsrow'];
        $this->titleForm = \ForkBB\__('Delete forum head');

        return $this;
    }

    /**
     * Редактирование раздела
     * Создание нового раздела
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function edit(array $args, $method)
    {
        $this->c->Lang->load('admin_forums');

        if (empty($args['id'])) {
            $forum           = $this->c->forums->create();
            $marker          = 'AdminForumsNew';
            $this->aCrumbs[] = [$this->c->Router->link($marker), \ForkBB\__('Add forum head')];
            $this->titleForm = \ForkBB\__('Add forum head');
            $this->classForm = 'createforum';
        } else {
            $forum           = $this->c->forums->loadTree((int) $args['id']); //?????
            $marker          = 'AdminForumsEdit';
            $this->aCrumbs[] = [$this->c->Router->link($marker, $args), \ForkBB\__('Edit forum head')];
            $this->aCrumbs[] = \ForkBB\__('"%s"', $forum->forum_name);
            $this->titleForm = \ForkBB\__('Edit forum head');
            $this->classForm = 'editforum';
        }

        if (! $forum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        }

        $this->calcList($forum);

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'                => 'token:' . $marker,
                    'forum_name'           => 'required|string:trim|max:80',
                    'forum_desc'           => 'string:trim|max:65000 bytes',
                    'parent'               => 'required|integer|in:' . implode(',', $this->listOfIndexes),
                    'sort_by'              => 'required|integer|in:0,1,2',
                    'redirect_url'         => 'string:trim|max:255', //????
                    'perms.*.read_forum'   => 'checkbox',
                    'perms.*.post_replies' => 'checkbox',
                    'perms.*.post_topics'  => 'checkbox',
                    'submit'               => 'string',
                    'reset'                => empty($forum->id) ? 'absent' : 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            $valid = $v->validation($_POST);

            $forum->forum_name   = $v->forum_name;
            $forum->forum_desc   = $v->forum_desc;
            $forum->sort_by      = $v->sort_by;
            $forum->redirect_url = $v->redirect_url;
            if ($v->parent > 0) {
                $forum->parent_forum_id = $v->parent;
                $forum->cat_id          = $this->c->forums->get($v->parent)->cat_id;
            } elseif ($v->parent < 0) {
                $forum->cat_id          = -$v->parent;
                $forum->parent_forum_id = 0;
            }

            if ($valid) {
                $this->c->DB->beginTransaction();

                if ($v->reset) {
                    $message = 'Perms reverted redirect';
                    $this->c->groups->Perm->reset($forum);
                } else {
                    if (empty($args['id'])) {
                        $message = 'Forum added redirect';
                        $forum->disp_position = $this->forumPos($forum);
                        $this->c->forums->insert($forum);
                    } else {
                        $message = 'Forum updated redirect';
                        $this->c->forums->update($forum);
                    }

                    $this->c->groups->Perm->update($forum, $v->perms);
                }

                $this->c->DB->commit();

                $this->c->Cache->delete('forums_mark');

                return $this->c->Redirect->page('AdminForumsEdit', ['id' => $forum->id])->message($message);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->nameTpl = 'admin/form';
        $this->aIndex  = 'forums';
        $this->form    = $this->viewForm($forum, $marker, $args);

        return $this;
    }

    /**
     * Формирует данные для формы редактирования раздела
     *
     * @param Forum $forum
     * @param string $marker
     * @param array $args
     *
     * @return array
     */
    protected function viewForm(Forum $forum, $marker, array $args)
    {
        $form = [
            'action' => $this->c->Router->link($marker, $args),
            'hidden' => [
                'token' => $this->c->Csrf->create($marker, $args),
            ],
            'sets'   => [],
            'btns'   => [],
        ];

        if ($forum->id > 0) {
            $form['btns']['reset'] = [
                'type'      => 'submit',
                'value'     => \ForkBB\__('Revert to default'),
                'accesskey' => 'r',
            ];
        }

        $form['btns']['submit'] = [
            'type'      => 'submit',
            'value'     => empty($forum->id) ? \ForkBB\__('Add') : \ForkBB\__('Update'),
            'accesskey' => 's',
        ];

        $form['sets']['forum'] = [
            'fields' => [
                'forum_name' => [
                    'type'      => 'text',
                    'maxlength' => 80,
                    'value'     => $forum->forum_name,
                    'caption'   => \ForkBB\__('Forum name label'),
                    'required'  => true,
                ],
                'forum_desc' => [
                    'type'    => 'textarea',
                    'value'   => $forum->forum_desc,
                    'caption' => \ForkBB\__('Forum description label'),
                ],
                'parent' => [
                    'type'     => 'select',
                    'options'  => $this->listForOptions,
                    'value'    => $forum->parent_forum_id ? $forum->parent_forum_id : -$forum->cat_id,
                    'caption'  => \ForkBB\__('Parent label'),
                    'info'     => \ForkBB\__('Parent help'),
                    'required' => true,
                ],
                'sort_by' => [
                    'type'    => 'select',
                    'options' => [
                        0 => \ForkBB\__('Last post option'),
                        1 => \ForkBB\__('Topic start option'),
                        2 => \ForkBB\__('Subject option'),
                    ],
                    'value'   => $forum->sort_by,
                    'caption' => \ForkBB\__('Sort by label'),
                ],
                'redirect_url' => [
                    'type'      => 'text',
                    'maxlength' => 255,
                    'value'     => $forum->redirect_url,
                    'caption'   => \ForkBB\__('Redirect label'),
                    'info'      => \ForkBB\__('Redirect help'),
                    'disabled'  => $forum->num_topics || $forum->subforums ? true : null,
                ],
            ],
        ];

        $form['sets']['forum-info'] = [
            'info' => [
                'info1' => [
                    'type'  => '', //????
                    'value' => \ForkBB\__('Group permissions info', $this->c->Router->link('AdminGroups'), \ForkBB\__('User groups')),
                    'html'  => true,
                ],
            ],
        ];

        $aOn  = ['cando', 'adm-inline', 'on'];
        $aOff = ['cando', 'adm-inline', 'off'];
        foreach ($this->c->groups->Perm->get($forum) as $id => $group) {
            $fieldset = [];
            $fieldset["perms[{$id}][read_forum]"] = [
                'class'    => $group->def_read_forum ? $aOn : $aOff,
                'type'     => 'checkbox',
                'value'    => '1',
                'caption'  => \ForkBB\__('Read forum label'),
                'label'    => \ForkBB\__('<span></span>'),
                'checked'  => $group->set_read_forum,
                'disabled' => $group->dis_read_forum,
            ];
            $fieldset["perms[{$id}][post_replies]"] = [
                'class'    => $group->def_post_replies ? $aOn : $aOff,
                'type'     => 'checkbox',
                'value'    => '1',
                'caption'  => \ForkBB\__('Post replies label'),
                'label'    => \ForkBB\__('<span></span>'),
                'checked'  => $group->set_post_replies,
                'disabled' => $group->dis_post_replies,
            ];
            $fieldset["perms[{$id}][post_topics]"] = [
                'class'    => $group->def_post_topics ? $aOn : $aOff,
                'type'     => 'checkbox',
                'value'    => '1',
                'caption'  => \ForkBB\__('Post topics label'),
                'label'    => \ForkBB\__('<span></span>'),
                'checked'  => $group->set_post_topics,
                'disabled' => $group->dis_post_topics,
            ];

            $form['sets']["perms{$id}"] = [
                'class'  => 'inline',
                'legend' => \ForkBB\e($group->g_title),
                'fields' => $fieldset,
            ];
        }

        return $form;
    }
}
