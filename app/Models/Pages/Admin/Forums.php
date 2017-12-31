<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Pages\Admin;

class Forums extends Admin
{
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
            $v = $this->c->Validator->setRules([
                'token'                => 'token:AdminForums',
                'form.*.disp_position' => 'required|integer|min:0|max:9999999999',
            ])->setArguments([
            ])->setMessages([
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
                'submit'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Update positions'),
                    'accesskey' => 'u',
                ],
            ],
        ];

        $root = $this->c->forums->get(0);
        $list = $this->createList([], $root, -1);

        $fieldset = [];
        $cid = null;
        foreach ($list as $forum) {
            if ($cid !== $forum->cat_id) {
                if (null !== $cid) {
                    $form['sets'][] = [
                        'fields' => $fieldset,
                    ];
                }

                $form['sets'][] = [
                    'info' => [
                        'info1' => [
                            'type'  => '', //????
                            'value' => $forum->cat_name,
                        ],
                    ],
                ];

                $fieldset = [];
            }
            $cid = $forum->cat_id;

            $fieldset[] = [
                'dl'        => ['name', 'depth' . $forum->depth],
                'type'      => 'btn',
                'value'     => $forum->forum_name,
                'title'     => \ForkBB\__('Forum label'),
                'link'      => $this->c->Router->link('AdminForumsEdit', ['id' => $forum->id]),
            ];
            $fieldset["form[{$forum->id}][disp_position]"] = [
                'dl'    => 'position',
                'type'  => 'number',
                'min'   => 0,
                'max'   => 9999999999,
                'value' => $forum->disp_position,
                'title' => \ForkBB\__('Position label'),
            ];
            $fieldset[] = [
                'dl'    => 'delete',
                'type'  => 'btn',
                'value' => '❌',
                'title' => \ForkBB\__('Delete'),
                'link'  => $this->c->Router->link('AdminForumsDelete', ['id' => $forum->id]),
            ];
        }

        $form['sets'][] = [
            'fields' => $fieldset,
        ];

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'forums';
        $this->titles    = \ForkBB\__('Forums');
        $this->form      = $form;
        $this->classForm = ['editforums', 'inline'];
        $this->titleForm = \ForkBB\__('Forums');

        return $this;
    }

    /**
     * Получение списка разделов и подразделов
     * 
     * @param array $list
     * @param Forum $forum
     * @param int $depth
     * 
     * @return array
     */
    protected function createList(array $list, Forum $forum, $depth)
    {
        ++$depth;
        foreach ($forum->subforums as $sub) {
            $sub->__depth = $depth;
            $list[] = $sub;
    
            $list = $this->createList($list, $sub, $depth);
        }
        return $list;
    }

    /**
     * Удаление категорий
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function delete(array $args, $method)
    {
        $forum = $this->c->forums->get((int) $args['id']);
        if (! $forum instanceof Forum) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('admin_forums');

        if ('POST' === $method) {
            $v = $this->c->Validator->setRules([
                'token'     => 'token:AdminForumsDelete',
                'confirm'   => 'integer',
                'delete'    => 'string',
                'cancel'    => 'string',
            ])->setArguments([
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
                'delete'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Delete forum'),
                    'accesskey' => 'd',
                ],
                'cancel'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Cancel'),
                ],
            ],
        ];

        $form['sets'][] = [
            'fields' => [
                'confirm' => [
                    'title'   => \ForkBB\__('Confirm delete'),
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
        $this->titles    = \ForkBB\__('Delete forum head');
        $this->form      = $form;
        $this->classForm = ['deleteforum', 'btnsrow'];
        $this->titleForm = \ForkBB\__('Delete forum head');

        return $this;
    }
}
