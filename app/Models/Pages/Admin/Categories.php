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
                $this->c->DB->beginTransaction();

                foreach ($v->form as $key => $row) {
                    $this->c->categories->set($key, $row);
                }
                $this->c->categories->update();

                if (\strlen($v->new) > 0) {
                    $this->c->categories->insert($v->new); //????
                }

                $this->c->DB->commit();

                $this->c->Cache->delete('forums_mark'); //????

                return $this->c->Redirect->page('AdminCategories')->message('Categories updated redirect');
            }

            $this->fIswev  = $v->getErrors();
        }

        $form = [
            'action' => $this->c->Router->link('AdminCategories'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminCategories'),
            ],
            'sets'   => [],
            'btns'   => [
                'submit'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Save changes'),
                    'accesskey' => 's',
                ],
            ],
        ];

        $fieldset = [];
        foreach ($this->c->categories->getList() as $key => $row) {
            $fieldset["form[{$key}][cat_name]"] = [
                'dl'        => ['name', 'inline'],
                'type'      => 'text',
                'maxlength' => 80,
                'value'     => $row['cat_name'],
                'title'     => \ForkBB\__('Category name label'),
                'required'  => true,
            ];
            $fieldset["form[{$key}][disp_position]"] = [
                'dl'    => ['position', 'inline'],
                'type'  => 'number',
                'min'   => 0,
                'max'   => 9999999999,
                'value' => $row['disp_position'],
                'title' => \ForkBB\__('Category position label'),
            ];
            $fieldset[] = [
                'dl'    => ['delete', 'inline'],
                'type'  => 'btn',
                'value' => '❌',
                'title' => \ForkBB\__('Delete'),
                'link'  => $this->c->Router->link('AdminCategoriesDelete', ['id' => $key]),
            ];
        }
        $form['sets'][] = ['fields' => $fieldset];
        $form['sets'][] = [
            'fields' => [
                'new' => [
                    'dl'        => 'new',
                    'type'      => 'text',
                    'maxlength' => 80,
                    'title'     => \ForkBB\__('Add category label'),
                    'info'      => \ForkBB\__('Add category help', $this->c->Router->link('AdminForums'), \ForkBB\__('Forums')),
                ],
            ],
        ];

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'categories';
        $this->titles    = \ForkBB\__('Categories');
        $this->form      = $form;
        $this->classForm = 'editcategories';
        $this->titleForm = \ForkBB\__('Categories');

        return $this;
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
                    'confirm'   => 'integer',
                    'delete'    => 'string',
                    'cancel'    => 'string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (! $v->validation($_POST) || null === $v->delete) {
                return $this->c->Redirect->page('AdminCategories')->message('Cancel redirect');
            } elseif ($v->confirm !== 1) {
                return $this->c->Redirect->page('AdminCategories')->message('No confirm redirect');
            }

            $this->c->DB->beginTransaction();

            $this->c->categories->delete((int) $args['id']);

            $this->c->DB->commit();

            $this->c->Cache->delete('forums_mark'); //????

            return $this->c->Redirect->page('AdminCategories')->message('Category deleted redirect');
        }

        $form = [
            'action' => $this->c->Router->link('AdminCategoriesDelete', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminCategoriesDelete', $args),
            ],
            'sets'   => [],
            'btns'   => [
                'delete'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Delete category'),
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
                    'label'   => \ForkBB\__('I want to delete the category %s', $category['cat_name']),
                    'value'   => '1',
                    'checked' => false,
                ],
            ],
        ];
        $form['sets'][] = [
            'info' => [
                'info1' => [
                    'type'  => '', //????
                    'value' => \ForkBB\__('Delete category warn'),
                    'html'  => true,
                ],
            ],
        ];

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'categories';
        $this->titles    = \ForkBB\__('Delete category head');
        $this->form      = $form;
        $this->classForm = ['deletecategory', 'btnsrow'];
        $this->titleForm = \ForkBB\__('Delete category head');

        return $this;
    }
}
