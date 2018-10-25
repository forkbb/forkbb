<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Pages\Admin;

class Categories extends Admin
{
    /**
     * Просмотр, редактирвоание и добавление категорий
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function view(array $args, $method)
    {
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

                $this->c->Cache->delete('forums_mark'); //????

                return $this->c->Redirect->page('AdminCategories')->message('Categories updated redirect');
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'categories';
        $this->form      = $this->formEdit();
        $this->classForm = 'editcategories';
        $this->titleForm = \ForkBB\__('Categories');

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @return array
     */
    protected function formEdit()
    {
        $form = [
            'action' => $this->c->Router->link('AdminCategories'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminCategories'),
            ],
            'sets'   => [],
            'btns'   => [
                'submit' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Save changes'),
                    'accesskey' => 's',
                ],
            ],
        ];

        foreach ($this->c->categories->getList() as $key => $row) {
            $fields = [];
            $fields["form[{$key}][cat_name]"] = [
                'class'     => ['name', 'category'],
                'type'      => 'text',
                'maxlength' => 80,
                'value'     => $row['cat_name'],
                'caption'   => \ForkBB\__('Category name label'),
                'required'  => true,
            ];
            $fields["form[{$key}][disp_position]"] = [
                'class'   => ['position', 'category'],
                'type'    => 'number',
                'min'     => 0,
                'max'     => 9999999999,
                'value'   => $row['disp_position'],
                'caption' => \ForkBB\__('Category position label'),
            ];
            $fields["delete-btn{$key}"] = [
                'class'   => ['delete', 'category'],
                'type'    => 'btn',
                'value'   => '❌',
                'caption' => \ForkBB\__('Delete'),
                'link'    => $this->c->Router->link('AdminCategoriesDelete', ['id' => $key]),
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
                    'maxlength' => 80,
                    'caption'   => \ForkBB\__('Add category label'),
                    'info'      => \ForkBB\__('Add category help', $this->c->Router->link('AdminForums'), \ForkBB\__('Forums')),
                ],
            ],
        ];

        return $form;
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
        $category = $this->c->categories->get((int) $args['id']);
        if (! $category) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('admin_categories');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:AdminCategoriesDelete',
                    'confirm'   => 'integer', // ????
                    'delete'    => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (! $v->validation($_POST) || $v->confirm !== 1) {
                return $this->c->Redirect->page('AdminCategories')->message('No confirm redirect');
            }

            $this->c->categories->delete((int) $args['id']);

            $this->c->Cache->delete('forums_mark'); //????

            return $this->c->Redirect->page('AdminCategories')->message('Category deleted redirect');
        }

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'categories';
        $this->aCrumbs[] = [$this->c->Router->link('AdminCategoriesDelete', ['id' => $args['id']]), \ForkBB\__('Delete category head')];
        $this->aCrumbs[] = \ForkBB\__('"%s"', $category['cat_name']);
        $this->form      = $this->formDelete($args, $category);
        $this->classForm = 'deletecategory';
        $this->titleForm = \ForkBB\__('Delete category head');

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     *
     * @param array $args
     * @param array $category
     *
     * @return array
     */
    protected function formDelete(array $args, array $category)
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
                            'caption' => \ForkBB\__('Confirm delete'),
                            'type'    => 'checkbox',
                            'label'   => \ForkBB\__('I want to delete the category %s', $category['cat_name']),
                            'value'   => '1',
                            'checked' => false,
                        ],
                    ],
                ],
                'del-info' => [
                    'info' => [
                        'info1' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Delete category warn'),
                            'html'  => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete' => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Delete category'),
                    'accesskey' => 's',
                ],
                'cancel' => [
                    'type'      => 'btn',
                    'value'     => \ForkBB\__('Cancel'),
                    'link'      => $this->c->Router->link('AdminCategories'),
                ],
            ],
        ];
    }
}
