<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Container;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\Pages\Admin;

class Categories extends Admin
{
    /**
     * Подготавливает данные для шаблона
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
            $v = $this->c->Validator->setRules([
                'token'                => 'token:AdminCategories',
                'form.*.cat_name'      => 'required|string:trim|max:80',
                'form.*.disp_position' => 'required|integer|min:0|max:9999999999',
                'new'                  => 'string:trim|max:80'
            ])->setArguments([
            ])->setMessages([
            ]);

            if ($v->validation($_POST)) {
                $this->c->DB->beginTransaction();

                foreach ($v->form as $key => $row) {
                    $this->c->categories->set($key, $row);
                }
                $this->c->categories->update();

                if (strlen($v->new) > 0) {
                    $this->c->categories->insert($v->new); //????
                }
                
                $this->c->DB->commit();

                $this->c->Cache->delete('forums_mark'); //????

                return $this->c->Redirect->page('AdminCategories')->message(\ForkBB\__('Categories updated redirect'));
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->nameTpl     = 'admin/categories';
        $this->aIndex      = 'categories';

        $form = [
            'action' => $this->c->Router->link('AdminCategories'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminCategories'),
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
        foreach ($this->c->categories->getList() as $key => $row) {

            $fieldset["form[{$key}][cat_name]"] = [
                'dl'        => 'name',
                'type'      => 'text',
                'maxlength' => 80,
                'value'     => $row['cat_name'],
                'title'     => \ForkBB\__('Category name label'),
                'required'  => true,
            ];
            $fieldset["form[{$key}][disp_position]"] = [
                'dl'    => 'position',
                'type'  => 'number',
                'min'   => 0,
                'max'   => 9999999999,
                'value' => $row['disp_position'],
                'title' => \ForkBB\__('Category position label'),
            ];

        }
        $fieldset['new'] = [
            'dl'        => 'new',
            'type'      => 'text',
            'maxlength' => 80,
            'title'     => \ForkBB\__('Add category label'),
            'info'      => \ForkBB\__('Add category help', $this->c->Router->link('AdminForums'), \ForkBB\__('Forums')),
        ];

        $form['sets'][] = [
            'fields' => $fieldset,
        ];

        $this->formUpdate = $form;

        return $this;
    }
}
