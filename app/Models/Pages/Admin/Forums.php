<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Page;
use ForkBB\Models\Forum\Forum;
use ForkBB\Models\Pages\Admin;
use function \ForkBB\__;

class Forums extends Admin
{
    /**
     * Составление списка категорий/разделов для выбора родителя
     */
    protected function calcList(Forum $forum): void
    {
        $cid        = null;
        $categories = $this->c->categories->repository;
        $options    = [
            ['', __('Not selected')],
        ];
        $idxs       = [];
        $root = $this->c->forums->get(0);

        if ($root instanceof Forum) {
            foreach ($this->c->forums->depthList($root, 0) as $f) {
                if ($cid !== $f->cat_id) {
                    $cid       = $f->cat_id;
                    $options[] = [-$cid, __('Category prefix') . $f->cat_name];
                    $idxs[]    = -$cid;
                    unset($categories[$cid]);
                }

                $indent = \str_repeat(__('Forum indent'), $f->depth);

                if (
                    $f->id === $forum->id
                    || isset($forum->descendants[$f->id])
                    || $f->redirect_url
                ) {
                    $options[] = [$f->id, $indent . __('Forum prefix') . $f->forum_name, true];

                } else {
                    $options[] = [$f->id, $indent . __('Forum prefix') . $f->forum_name];
                    $idxs[]    = $f->id;
                }
            }
        }

        foreach ($categories as $key => $row) {
            $idxs[]    = -$key;
            $options[] = [-$key, __('Category prefix') . $row['cat_name']];
        }

        $this->listOfIndexes  = $idxs;
        $this->listForOptions = $options;
    }

    /**
     * Вычисление позиции для (нового) раздела
     */
    protected function forumPos(Forum $forum): int
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
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_forums');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'                => 'token:AdminForums',
                    'form'                 => 'required|array',
                    'form.*.disp_position' => 'required|integer|min:0|max:9999999999',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                foreach ($v->form as $key => $row) {
                    $forum = $this->c->forums->get((int) $key);
                    $forum->disp_position = $row['disp_position'];
                    $this->c->forums->update($forum);
                }

                $this->c->forums->reset();

                return $this->c->Redirect->page('AdminForums')->message('Forums updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'forums';
        $this->form      = $this->formView();
        $this->classForm = ['editforums', 'inline'];
        $this->titleForm = 'Forums';

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formView(): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminForums'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminForums'),
            ],
            'sets'   => [],
            'btns'   => [
                'new' => [
                    'type'  => 'btn',
                    'value' => __('New forum'),
                    'href'  => $this->c->Router->link('AdminForumsNew'),
                ],
                'update' => [
                    'type'  => 'submit',
                    'value' => __('Update positions'),
                ],
            ],
        ];

        $root = $this->c->forums->get(0);

        if ($root instanceof Forum) {
            $list = $this->c->forums->depthList($root, -1);
            $cid  = null;

            foreach ($list as $forum) {
                if ($cid !== $forum->cat_id) {
                    $form['sets']["category{$forum->cat_id}-info"] = [
                        'inform' => [
                            [
                                'message' => $forum->cat_name,
                            ],
                        ],
                    ];
                    $cid = $forum->cat_id;
                }

                $fields = [];
                $fields["name-btn{$forum->id}"] = [
                    'class'   => ['name', 'forum', 'depth' . $forum->depth],
                    'type'    => 'btn',
                    'value'   => $forum->forum_name,
                    'caption' => 'Forum label',
                    'href'    => $this->c->Router->link(
                        'AdminForumsEdit',
                        [
                            'id' => $forum->id,
                        ]
                    ),
                ];
                $fields["form[{$forum->id}][disp_position]"] = [
                    'class'   => ['position', 'forum'],
                    'type'    => 'number',
                    'min'     => '0',
                    'max'     => '9999999999',
                    'value'   => $forum->disp_position,
                    'caption' => 'Position label',
                ];
                $disabled = (bool) $forum->subforums;
                $fields["delete-btn{$forum->id}"] = [
                    'class'    => ['delete', 'forum'],
                    'type'     => 'btn',
                    'value'    => '❌',
                    'caption'  => 'Delete',
                    'title'    => __('Delete'),
                    'href'     => $disabled
                        ? '#'
                        : $this->c->Router->link(
                            'AdminForumsDelete',
                            [
                                'id' => $forum->id,
                            ]
                        ),
                    'disabled' => $disabled,
                ];
                $form['sets']["forum{$forum->id}"] = [
                    'class'  => ['forum', 'inline'],
                    'legend' => $forum->cat_name . ' / ' . $forum->forum_name,
                    'fields' => $fields,
                ];
            }
        }

        return $form;
    }

    /**
     * Удаление раздела
     */
    public function delete(array $args, string $method): Page
    {
        $forum = $this->c->forums->get($args['id']);

        if (
            ! $forum instanceof Forum
            || $forum->subforums
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_forums');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:AdminForumsDelete',
                    'confirm'   => 'checkbox',
                    'delete'    => 'required|string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (
                ! $v->validation($_POST)
                || '1' !== $v->confirm
            ) {
                return $this->c->Redirect->page('AdminForums')->message('No confirm redirect', FORK_MESS_WARN);
            }

            $this->c->forums->delete($forum);
            $this->c->forums->reset();

            return $this->c->Redirect->page('AdminForums')->message('Forum deleted redirect', FORK_MESS_SUCC);
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'forums';
        $this->aCrumbs[] = [
            $this->c->Router->link(
                'AdminForumsDelete',
                [
                    'id' => $forum->id,
                ]
            ),
            'Delete forum head',
        ];
        $this->aCrumbs[] = [null, ['"%s"', $forum->forum_name]];
        $this->form      = $this->formDelete($args, $forum);
        $this->classForm = ['deleteforum'];
        $this->titleForm = 'Delete forum head';

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formDelete(array $args, Forum $forum): array
    {
        return [
            'action' => $this->c->Router->link('AdminForumsDelete', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminForumsDelete', $args),
            ],
            'sets'   => [
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'caption' => 'Confirm delete',
                            'type'    => 'checkbox',
                            'label'   => ['I want to delete forum %s', $forum->forum_name],
                            'checked' => false,
                        ],
                    ],
                ],
                [
                    'inform' => [
                        [
                            'message' => 'Delete forum warn',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete' => [
                    'type'  => 'submit',
                    'value' => __('Delete forum'),
                ],
                'cancel' => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'href'  => $this->c->Router->link('AdminForums'),
                ],
            ],
        ];
    }

    /**
     * Редактирование раздела
     * Создание нового раздела
     */
    public function edit(array $args, string $method): Page
    {
        $forum = empty($args['id']) ? $this->c->forums->create() : $this->c->forums->loadTree($args['id']);

        if (! $forum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_forums');

        if (empty($args['id'])) {
            $marker          = 'AdminForumsNew';
            $this->aCrumbs[] = [$this->c->Router->link($marker), 'Add forum head'];
            $this->titleForm = 'Add forum head';
            $this->classForm = ['createforum'];

        } else {
            $marker          = 'AdminForumsEdit';
            $this->aCrumbs[] = [$this->c->Router->link($marker, $args), 'Edit forum head'];
            $this->aCrumbs[] = [null, ['"%s"', $forum->forum_name]];
            $this->titleForm = 'Edit forum head';
            $this->classForm = ['editforum'];
        }

        $this->calcList($forum);

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'                => 'token:' . $marker,
                    'forum_name'           => 'required|string:trim|max:80',
                    'friendly_name'        => 'string:trim|max:80|regex:%^[\w-]*$%',
                    'forum_desc'           => 'exist|string:trim|max:65000 bytes|html',
                    'parent'               => 'required|integer|in:' . \implode(',', $this->listOfIndexes),
                    'sort_by'              => 'required|integer|in:0,1,2,4,5,6',
                    'redirect_url'         => 'string:trim|max:255|regex:%^(?:https?://.+)?$%', //???? это поле может быть отключено в форме
                    'no_sum_mess'          => 'required|integer|in:0,1',
                    'use_solution'         => 'required|integer|in:0,1',
                    'perms.*.read_forum'   => 'checkbox',
                    'perms.*.post_replies' => 'checkbox',
                    'perms.*.post_topics'  => 'checkbox',
                    'submit'               => 'string',
                    'reset'                => $forum->id > 0 ? 'string' : 'absent',
                    'use_custom_fields'    => $forum->id > 0 && ! $forum->redirect_url ? 'required|integer|in:0,1' : 'absent',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            $valid = $v->validation($_POST);

            $forum->forum_name    = $v->forum_name;
            $forum->friendly_name = \trim($v->friendly_name, '_-');
            $forum->forum_desc    = $v->forum_desc;
            $forum->sort_by       = $v->sort_by;
            $forum->redirect_url  = $v->redirect_url ?? '';
            $forum->no_sum_mess   = $v->no_sum_mess;
            $forum->use_solution  = $v->use_solution;

            if ($forum->id > 0) {
                $forum->use_custom_fields = $forum->redirect_url || empty($forum->custom_fields) ? 0 : $v->use_custom_fields;
            }

            if ($v->parent > 0) {
                $forum->parent_forum_id = $v->parent;
                $forum->cat_id          = $this->c->forums->get($v->parent)->cat_id;

            } elseif ($v->parent < 0) {
                $forum->cat_id          = -$v->parent;
                $forum->parent_forum_id = 0;
            }

            if ($valid) {
                if ($v->reset) {
                    $message = 'Perms reverted redirect';
                    $this->c->groups->Perm->reset($forum);

                } else {
                    if (empty($args['id'])) {
                        $message = 'Forum added redirect';
                        $forum->disp_position = $this->forumPos($forum);
                        $forum->moderators    = '';
                        $this->c->forums->insert($forum);

                    } else {
                        $message = 'Forum updated redirect';
                        $this->c->forums->update($forum);
                    }

                    $this->c->groups->Perm->update($forum, $v->perms);
                }

                $this->c->forums->reset();

                return $this->c->Redirect->page('AdminForumsEdit', ['id' => $forum->id])->message($message, FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->nameTpl = 'admin/form';
        $this->aIndex  = 'forums';
        $this->form    = $this->formEdit($args, $forum, $marker);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formEdit(array $args, Forum $forum, string $marker): array
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
                'type'  => 'submit',
                'value' => __('Revert to default'),
                'class' => ['f-opacity'],
            ];
        }

        $form['btns']['submit'] = [
            'type'  => 'submit',
            'value' =>  __($forum->id > 0 ? 'Update' : 'Add'),
        ];

        $form['sets']['forum'] = [
            'fields' => [
                'forum_name' => [
                    'type'      => 'text',
                    'maxlength' => '80',
                    'value'     => $forum->forum_name,
                    'caption'   => 'Forum name label',
                    'required'  => true,
                ],
                'friendly_name' => [
                    'type'      => 'text',
                    'maxlength' => '80',
                    'value'     => $forum->friendly_name,
                    'caption'   => 'Friendly name label',
                    'help'      => 'Friendly name help',
                ],
                'forum_desc' => [
                    'type'    => 'textarea',
                    'value'   => $forum->forum_desc,
                    'caption' => 'Forum description label',
                ],
                'parent' => [
                    'type'     => 'select',
                    'options'  => $this->listForOptions,
                    'value'    => $forum->parent_forum_id ? $forum->parent_forum_id : -$forum->cat_id,
                    'caption'  => 'Parent label',
                    'help'     => 'Parent help',
                    'required' => true,
                ],
                'sort_by' => [
                    'type'    => 'select',
                    'options' => [
                        0 => __('Last post option DESC'),
                        1 => __('Topic start option DESC'),
                        2 => __('Subject option ASC'),
                        4 => __('Last post option ASC'),
                        5 => __('Topic start option ASC'),
                        6 => __('Subject option DESC'),
                    ],
                    'value'   => $forum->sort_by,
                    'caption' => 'Sort by label',
                ],
                'redirect_url' => [
                    'type'      => 'text',
                    'maxlength' => '255',
                    'value'     => $forum->redirect_url,
                    'caption'   => 'Redirect label',
                    'help'      => 'Redirect help',
                    'disabled'  => $forum->num_topics || $forum->subforums ? true : null,
                ],
                'no_sum_mess' => [
                    'type'    => 'radio',
                    'value'   => $forum->no_sum_mess,
                    'values'  => [0 => __('Yes'), 1 => __('No')],
                    'caption' => 'Count messages label',
                    'help'    => ['Count messages help', $this->c->Router->link('AdminUsers', ['#' => 'fork-recalc']), __('Users')],
                ],
                'use_solution' => [
                    'type'    => 'radio',
                    'value'   => $forum->use_solution,
                    'values'  => [1 => __('Yes'), 0 => __('No')],
                    'caption' => 'Use solution label',
                    'help'    => 'Use solution help',
                ],
            ],
        ];

        if (
            $forum->id > 0
            && ! $forum->redirect_url
        ) {
            $form['sets']['forum']['fields'] += [
                'use_custom_fields' => [
                    'type'    => 'radio',
                    'value'   => $forum->use_custom_fields,
                    'values'  => [1 => __('Yes'), 0 => __('No')],
                    'caption' => 'Use custom fields label',
                    'help'    => 'Use custom fields help',
                ],
                'custom_fields' => [
                    'type'    => 'link',
                    'value'   => __('Customize fields'),
                    'title'   => __('Customize fields'),
                    'href'    => $this->c->Router->link('AdminForumsFields', ['id' => $forum->id]),
                ],
            ];
        }

        $form['sets']['forum-info'] = [
            'inform' => [
                [
                    'message' => ['Group permissions info', $this->c->Router->link('AdminGroups'), __('User groups')],
                ],
            ],
        ];

        $aOn  = ['cando', 'on'];
        $aOff = ['cando', 'off'];

        foreach ($this->c->groups->Perm->get($forum) as $id => $group) {
            $fields = [];
            $fields["perms[{$id}][read_forum]"] = [
                'class'    => $group->def_read_forum ? $aOn : $aOff,
                'type'     => 'checkbox',
                'caption'  => 'Read forum label',
                'label'    => '<span></span>',
                'checked'  => $group->set_read_forum,
                'disabled' => $group->dis_read_forum,
            ];
            $fields["perms[{$id}][post_replies]"] = [
                'class'    => $group->def_post_replies ? $aOn : $aOff,
                'type'     => 'checkbox',
                'caption'  => 'Post replies label',
                'label'    => '<span></span>',
                'checked'  => $group->set_post_replies,
                'disabled' => $group->dis_post_replies,
            ];
            $fields["perms[{$id}][post_topics]"] = [
                'class'    => $group->def_post_topics ? $aOn : $aOff,
                'type'     => 'checkbox',
                'caption'  => 'Post topics label',
                'label'    => '<span></span>',
                'checked'  => $group->set_post_topics,
                'disabled' => $group->dis_post_topics,
            ];

            $form['sets']["perms{$id}"] = [
                'class'  => ['permission'],
                'legend' => $group->g_title,
                'fields' => $fields,
            ];
        }

        return $form;
    }

    /**
     * Точка входа для настраиваемых полей
     */
    public function customFields(array $args, string $method): Page
    {
        $forum = $this->c->forums->loadTree($args['id']);

        if (
            ! $forum instanceof Forum
            || $forum->redirect_url
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_forums');

        if (empty($args['action'])) {
            $m = 'customFieldsView';

        } else {
            switch ($args['action']) {
                case 'new':
                case 'edit':
                    $m = 'customFieldsEdit';

                    break;
                case 'delete':
                    $m = 'customFieldsDelete';

                    break;
                default:
                    return $this->c->Message->message('Bad request');
            }
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'forums';
        $page            = $this->{$m}($args, $method, $forum);
        $this->aCrumbs[] = [$this->c->Router->link('AdminForumsFields', ['id' => $forum->id]), 'Custom fields head'];
        $this->aCrumbs[] = [$this->c->Router->link('AdminForumsEdit', $args), 'Edit forum head'];
        $this->aCrumbs[] = [null, ['"%s"', $forum->forum_name]];

        return $page;
    }

    /**
     * Список настраиваемых полей
     */
    protected function customFieldsView(array $args, string $method, Forum $forum): Page
    {
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token' => 'token:AdminForumsFields',
                    'pos'   => 'required|array',
                    'pos.*' => 'required|integer|min:0|max:9999',
                ])->addAliases([
                ])->addArguments([
                    'token' => ['id' => $forum->id],
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $positions = $v->pos;

                \asort($positions, \SORT_NUMERIC);

                $positions = \array_keys($positions);
                $i         = 1;
                $fields    = $forum->custom_fields;
                $new       = [];

                foreach ($positions as $pos) {
                    if (empty($fields[$pos])) {
                        continue;
                    }

                    $new[$i]      = $fields[$pos];
                    $fields[$pos] = null;
                    ++$i;
                }

                foreach ($fields as $field) {
                    if (empty($field)) {
                        continue;
                    }

                    $new[$i] = $field;
                    ++$i;
                }

                $forum->custom_fields = $new;

                $this->c->forums->update($forum);

                return $this->c->Redirect->page('AdminForumsFields', $args)->message('Fields updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->titleForm = 'Custom fields head';
        $this->classForm = ['editforums', 'customfields', 'inline'];
        $this->form      = $this->formCFView($forum, $args);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formCFView(Forum $forum, array $args): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminForumsFields', ['id' => $forum->id]),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminForumsFields', ['id' => $forum->id]),
            ],
            'sets'   => [],
            'btns'   => [
                'new' => [
                    'type'  => 'btn',
                    'value' => __('Add field'),
                    'href'  => $this->c->Router->link(
                        'AdminForumsFields',
                        [
                            'id'     => $forum->id,
                            'action' => 'new',
                        ]
                    ),
                ],
                'update' => [
                    'type'  => 'submit',
                    'value' => __('Update positions'),
                ],
            ],
        ];

        $list = $forum->custom_fields;

        foreach ($list as $id => $cur) {
            $fields = [];
            $fields["name-btn{$id}"] = [
                'class'   => ['name', 'forum'],
                'type'    => 'btn',
                'value'   => $cur['name'],
                'caption' => 'Field name label',
                'href'    => $this->c->Router->link(
                    'AdminForumsFields',
                    [
                        'id'     => $forum->id,
                        'action' => 'edit',
                        'field'  => $id,
                    ]
                ),
            ];
            $fields["pos[{$id}]"] = [
                'class'   => ['position', 'forum'],
                'type'    => 'number',
                'min'     => '1',
                'max'     => '9999',
                'value'   => $id,
                'caption' => 'Position label',
            ];
            $fields["delete-btn{$id}"] = [
                'class'    => ['delete', 'forum'],
                'type'     => 'btn',
                'value'    => '❌',
                'caption'  => 'Delete',
                'title'    => __('Delete'),
                'href'     => $this->c->Router->link(
                    'AdminForumsFields',
                    [
                        'id'     => $forum->id,
                        'action' => 'delete',
                        'field'  => $id,
                    ]
                ),
            ];
            $form['sets']["field{$id}"] = [
                'class'  => ['forum', 'inline'],
                'legend' => $cur['name'],
                'fields' => $fields,
            ];
        }

        return $form;
    }

    /**
     * Создание/редактирование поля
     */
    protected function customFieldsEdit(array $args, string $method, Forum $forum): Page
    {
        if (empty($args['field'])) {
            $this->titleForm = 'Add field head';
            $this->classForm = ['createfield'];
            $data            = [];

        } else {
            if (empty($forum->custom_fields[$args['field']])) {
                return $this->c->Message->message('Bad request');
            }

            $this->titleForm = 'Edit field head';
            $this->classForm = ['editfield'];
            $data            = $forum->custom_fields[$args['field']];
        }

        $this->aCrumbs[] = [$this->c->Router->link('AdminForumsFields', $args), $this->titleForm];

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'      => 'token:AdminForumsFields',
                    'name'       => 'required|string|max:256',
                    'required'   => 'required|integer|in:0,1',
                    'visibility' => 'required|integer|in:1,2,3,4',
                    'maxlength'  => 'required|integer|min:1|max:10000',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ])->addMessages([
                ]);

            $valid = $v->validation($_POST);
            $data  = $v->getData(true, ['token']);

            if ($valid) {
                $fields               = $forum->custom_fields;
                $id                   = empty($args['field']) ? \max(1, \max(\array_keys($fields) + [0]) + 1) : $args['field'];
                $fields[$id]          = $data;
                $forum->custom_fields = $fields;

                $this->c->forums->update($forum);

                return $this->c->Redirect->page('AdminForumsFields', ['id' => $forum->id])->message('Fields updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->form = $this->formCFEdit($forum, $args, $data);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formCFEdit(Forum $forum, array $args, array $data): array
    {
        return [
            'action' => $this->c->Router->link('AdminForumsFields', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminForumsFields', $args),
            ],
            'sets'   => [
                'field' => [
                    'fields' => [
                        'name' => [
                            'type'      => 'text',
                            'maxlength' => '255',
                            'value'     => $data['name'] ?? '',
                            'caption'   => 'Field name label',
                            'required'  => true,
                        ],
                        'required' => [
                            'type'    => 'radio',
                            'value'   => ($data['required'] ?? 1) ? 1 : 0,
                            'values'  => [1 => __('Yes'), 0 => __('No')],
                            'caption' => 'Field required label',
                        ],
                        'visibility' => [
                            'type'    => 'select',
                            'options' => [
                                1 => __('All'),
                                2 => __('Not guests'),
                                3 => __('Admins and mods'),
                                4 => __('Admins only'),
                            ],
                            'value'   => $data['visibility'] ?? 4,
                            'caption' => 'Visibility label',
                        ],
                        'maxlength' => [
                            'type'    => 'number',
                            'min'     => '1',
                            'max'     => '10000',
                            'value'   => $data['maxlength'] ?? 256,
                            'caption' => 'Maximum length label',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __(empty($args['field']) ? 'Add' : 'Update'),
                ]
            ],
        ];
    }

    /**
     * Удаление поля
     */
    protected function customFieldsDelete(array $args, string $method, Forum $forum): Page
    {
        if (empty($forum->custom_fields[$args['field']])) {
            return $this->c->Message->message('Bad request');
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:AdminForumsFields',
                    'confirm'   => 'checkbox',
                    'delete'    => 'required|string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            $page = $this->c->Redirect->page('AdminForumsFields', ['id' => $forum->id]);

            if (
                ! $v->validation($_POST)
                || '1' !== $v->confirm
            ) {
                return $page->message('No confirm redirect', FORK_MESS_WARN);
            }

            $fields = $forum->custom_fields;

            unset($fields[$args['field']]);

            $forum->custom_fields = $fields;

            $this->c->forums->update($forum);

            return $page->message('Field deleted redirect', FORK_MESS_SUCC);
        }

        $this->titleForm = 'Delete field head';
        $this->classForm = ['deletefield', 'deleteforum'];
        $this->aCrumbs[] = [$this->c->Router->link('AdminForumsFields', $args), $this->titleForm];
        $this->form      = $this->formCFDelete($forum, $args);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formCFDelete(Forum $forum, array $args): array
    {
        return [
            'action' => $this->c->Router->link('AdminForumsFields', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminForumsFields', $args),
            ],
            'sets'   => [
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'caption' => 'Confirm delete',
                            'type'    => 'checkbox',
                            'label'   => ['I want to delete field %s', $forum->custom_fields[$args['field']]['name']],
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete' => [
                    'type'  => 'submit',
                    'value' => __('Delete field'),
                ],
                'cancel' => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'href'  => $this->c->Router->link('AdminForumsFields', ['id' => $forum->id]),
                ],
            ],
        ];
    }
}
