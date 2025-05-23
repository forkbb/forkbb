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

class Categories extends Admin
{
    /**
     * Просмотр, редактирвоание и добавление категорий
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_categories');

        $list = $this->c->categories->repository;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'                => 'token:AdminCategories',
                    'new'                  => 'exist|string:trim|max:80'
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if (! empty($list)) {
                $v->addRules([
                    'form'                 => 'required|array',
                    'form.*.cat_name'      => 'required|string:trim|max:80',
                    'form.*.disp_position' => 'required|integer|min:0|max:9999999999',
                ]);
            }

            if ($v->validation($_POST)) {
                if (! empty($list)) {
                    foreach ($v->form as $key => $row) {
                        $this->c->categories->set($key, $row);
                    }

                    $this->c->categories->update();
                }

                if (\strlen($v->new) > 0) {
                    $this->c->categories->insert($v->new);
                }

                $this->c->forums->reset();

                return $this->c->Redirect->page('AdminCategories')->message('Categories updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'categories';
        $this->form      = $this->formEdit();
        $this->classForm = ['editcategories', 'inline'];
        $this->titleForm = 'Categories';

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formEdit(): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminCategories'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminCategories'),
            ],
            'sets'   => [],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        foreach ($this->c->categories->repository as $key => $row) {
            $fields = [];
            $fields["form[{$key}][cat_name]"] = [
                'class'     => ['name', 'category'],
                'type'      => 'text',
                'maxlength' => '80',
                'value'     => $row['cat_name'],
                'caption'   => 'Category name label',
                'required'  => true,
            ];
            $fields["form[{$key}][disp_position]"] = [
                'class'   => ['position', 'category'],
                'type'    => 'number',
                'min'     => '0',
                'max'     => '9999999999',
                'value'   => $row['disp_position'],
                'caption' => 'Category position label',
            ];
            $fields["delete-btn{$key}"] = [
                'class'   => ['delete', 'category'],
                'type'    => 'btn',
                'value'   => '❌',
                'caption' => 'Delete',
                'title'   => __('Delete'),
                'href'    => $this->c->Router->link(
                    'AdminCategoriesDelete',
                    [
                        'id' => $key,
                    ]
                ),
            ];
            $form['sets']["category{$key}"] = [
                'class'  => ['category', 'inline'],
                'legend' => $row['cat_name'],
                'fields' => $fields,
            ];
        }

        $form['sets']['new-cat'] = [
            'fields' => [
                'new' => [
                    'class'     => ['new'],
                    'type'      => 'text',
                    'maxlength' => '80',
                    'caption'   => 'Add category label',
                    'help'      => ['Add category help', $this->c->Router->link('AdminForums'), __('Forums')],
                ],
            ],
        ];

        return $form;
    }


    /**
     * Удаление категорий
     */
    public function delete(array $args, string $method): Page
    {
        $category = $this->c->categories->get($args['id']);

        if (! $category) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_categories');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:AdminCategoriesDelete',
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
                return $this->c->Redirect->page('AdminCategories')->message('No confirm redirect', FORK_MESS_WARN);
            }

            $this->c->categories->delete($args['id']);

            $this->c->forums->reset();

            return $this->c->Redirect->page('AdminCategories')->message('Category deleted redirect', FORK_MESS_SUCC);
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'categories';
        $this->aCrumbs[] = [$this->c->Router->link('AdminCategoriesDelete', $args), 'Delete category head'];
        $this->aCrumbs[] = [null, ['"%s"', $category['cat_name']]];
        $this->form      = $this->formDelete($args, $category);
        $this->classForm = ['deletecategory'];
        $this->titleForm = 'Delete category head';

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formDelete(array $args, array $category): array
    {
        return [
            'action' => $this->c->Router->link('AdminCategoriesDelete', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminCategoriesDelete', $args),
            ],
            'sets'   => [
                'del' => [
                    'fields' => [
                        'confirm' => [
                            'caption' => 'Confirm delete',
                            'type'    => 'checkbox',
                            'label'   => ['I want to delete the category %s', $category['cat_name']],
                            'checked' => false,
                        ],
                    ],
                ],
                'del-info' => [
                    'inform' => [
                        [
                            'message' => 'Delete category warn',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete' => [
                    'type'  => 'submit',
                    'value' => __('Delete category'),
                ],
                'cancel' => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'href'  => $this->c->Router->link('AdminCategories'),
                ],
            ],
        ];
    }
}
