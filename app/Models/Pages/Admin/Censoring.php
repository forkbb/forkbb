<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Models\Pages\Admin;

class Censoring extends Admin
{
    /**
     * Просмотр, редактирвоание и добавление запрещенных слов
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function edit(array $args, $method)
    {
        $this->c->Lang->load('admin_censoring');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'               => 'token:AdminCensoring',
                    'o_censoring'         => 'required|integer|in:0,1',
                    'form.*.search_for'   => 'string:trim|max:60',
                    'form.*.replace_with' => 'string:trim|max:60',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $this->c->DB->beginTransaction();

                $this->c->config->o_censoring = $v->o_censoring;
                $this->c->config->save();

                $this->c->censorship->save($v->form);

                $this->c->DB->commit();

                $this->c->Cache->delete('censorship'); //????

                return $this->c->Redirect->page('AdminCensoring')->message('Data updated redirect');
            }

            $this->fIswev  = $v->getErrors();
        }

        $form = [
            'action' => $this->c->Router->link('AdminCensoring'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminCensoring'),
            ],
            'sets'   => [
                [
                    'fields' => [
                        'o_censoring' => [
                            'type'   => 'radio',
                            'value'  => $this->c->config->o_censoring,
                            'values' => [1 => \ForkBB\__('Yes'), 0 => \ForkBB\__('No')],
                            'title'  => \ForkBB\__('Censor words label'),
                            'info'   => \ForkBB\__('Censor words help'),
                        ],
                    ],
                ],
                [
                    'info' => [
                        'info1' => [
                            'type'  => '', //????
                            'value' => \ForkBB\__('Censoring info'),
                            'html'  => true,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'sva'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Save changes'),
                    'accesskey' => 's',
                ],
            ],
        ];

        $fieldset = [];
        foreach ($this->c->censorship->load() as $id => $row) {
            $fieldset["form[{$id}][search_for]"] = [
                'dl'        => ['censor', 'inline'],
                'type'      => 'text',
                'maxlength' => 60,
                'value'     => $row['search_for'],
                'title'     => \ForkBB\__('Censored word label'),
            ];
            $fieldset["form[{$id}][replace_with]"] = [
                'dl'        => ['censor', 'inline'],
                'type'      => 'text',
                'maxlength' => 60,
                'value'     => $row['replace_with'],
                'title'     => \ForkBB\__('Replacement label'),
            ];
        }
        $fieldset["form[0][search_for]"] = [
            'dl'        => ['censor', 'inline'],
            'type'      => 'text',
            'maxlength' => 60,
            'value'     => '',
            'title'     => \ForkBB\__('Censored word label'),
        ];
        $fieldset["form[0][replace_with]"] = [
            'dl'        => ['censor', 'inline'],
            'type'      => 'text',
            'maxlength' => 60,
            'value'     => '',
            'title'     => \ForkBB\__('Replacement label'),
        ];

        $form['sets'][] = [
            'fields' => $fieldset,
        ];

        $this->nameTpl   = 'admin/form';
        $this->aIndex    = 'censoring';
        $this->titles    = \ForkBB\__('Censoring');
        $this->form      = $form;
        $this->classForm = 'editcensorship';
        $this->titleForm = \ForkBB\__('Censoring');

        return $this;
    }
}
