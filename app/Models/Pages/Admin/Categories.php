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
use ForkBB\Models\Forum\Model as Forum;
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

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'                => 'token:AdminCategories',
                    'form.*.cat_name'      => 'required|string:trim|max:80',
                    'form.*.disp_position' => 'required|integer|min:0|max:9999999999',
                    'new'                  => 'string:trim|max:80'
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                foreach ($v->form as $key => $row) {
                    $this->c->categories->set($key, $row);
                }
                $this->c->categories->update();

                if (\strlen($v->new) > 0) {
                    $this->c->categories->insert($v->new); //????
                }

                $this->c->forums->reset();

                return $this->c->Redirect->page('AdminCategories')->message('Categories updated redirect');
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'categories';
        $this->form      = $this->formEdit();
        $this->classForm = 'editcategories';
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

        foreach ($this->c->categories->getList() as $key => $row) {
            $fields = [];
            $fields["form[{$key}][cat_name]"] = [
                'class'     => ['name', 'category'],
                'type'      => 'text',
                'maxlength' => '80',
                'value'     => $row['cat_name'],
                'caption'   => __('Category name label'),
                'required'  => true,
            ];
            $fields["form[{$key}][disp_position]"] = [
                'class'   => ['position', 'category'],
                'type'    => 'number',
                'min'     => '0',
                'max'     => '9999999999',
                'value'   => $row['disp_position'],
                'caption' => __('Category position label'),
            ];
            $fields["delete-btn{$key}"] = [
                'class'   => ['delete', 'category'],
                'type'    => 'btn',
                'value'   => '❌',
                'caption' => __('Delete'),
                'title'   => __('Delete'),
                'link'    => $this->c->Router->link(
                    'AdminCategoriesDelete',
                    [
                        'id' => $key,
                    ]
                ),
            ];
            $form['sets']["category{$key}"] = [
                'class'  => 'category',
                'legend' => $row['cat_name'],
                'fields' => $fields,
            ];
        }

        $form['sets']['new-cat'] = [
            'fields' => [
                'new' => [
                    'class'     => 'new',
                    'type'      => 'text',
                    'maxlength' => '80',
                    'caption'   => __('Add category label'),
                    'info'      => __(['Add category help', $this->c->Router->link('AdminForums'), __('Forums')]),
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
                    'delete'    => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (
                ! $v->validation($_POST)
                || '1' !== $v->confirm
            ) {
                return $this->c->Redirect->page('AdminCategories')->message('No confirm redirect');
            }

            $this->c->categories->delete($args['id']);

            $this->c->forums->reset();

            return $this->c->Redirect->page('AdminCategories')->message('Category deleted redirect');
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'categories';
        $this->aCrumbs[] = [
            $this->c->Router->link('AdminCategoriesDelete', $args),
            __('Delete category head'),
        ];
        $this->aCrumbs[] = __(['"%s"', $category['cat_name']]);
        $this->form      = $this->formDelete($args, $category);
        $this->classForm = 'deletecategory';
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
                            'caption' => __('Confirm delete'),
                            'type'    => 'checkbox',
                            'label'   => __(['I want to delete the category %s', $category['cat_name']]),
                            'value'   => '1',
                            'checked' => false,
                        ],
                    ],
                ],
                'del-info' => [
                    'info' => [
                        'info1' => [
                            'value' => __('Delete category warn'),
                            'html'  => true,
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
                    'link'  => $this->c->Router->link('AdminCategories'),
                ],
            ],
        ];
    }
}
