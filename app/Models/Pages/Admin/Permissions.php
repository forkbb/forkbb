<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Config as Config;

class Permissions extends Admin
{
    /**
     * Редактирование натроек форума
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function edit(array $args, $method)
    {
        $this->c->Lang->load('admin_permissions');

        $config = clone $this->c->config;

        if ('POST' === $method) {
            $v = $this->c->Validator->addValidators([
            ])->setRules([
                'token'                   => 'token:AdminPermissions',
                'p_message_bbcode'        => 'required|integer|in:0,1',
                'p_message_img_tag'       => 'required|integer|in:0,1',
                'p_message_all_caps'      => 'required|integer|in:0,1',
                'p_subject_all_caps'      => 'required|integer|in:0,1',
                'p_force_guest_email'     => 'required|integer|in:0,1',
                'p_sig_bbcode'            => 'required|integer|in:0,1',
                'p_sig_img_tag'           => 'required|integer|in:0,1',
                'p_sig_all_caps'          => 'required|integer|in:0,1',
                'p_sig_length'            => 'required|integer|min:0|max:16000',
                'p_sig_lines'             => 'required|integer|min:0|max:100',
            ])->setArguments([
            ])->setMessages([
            ]);

            $valid = $v->validation($_POST);
            $data  = $v->getData();

            unset($data['token']);

            foreach ($data as $attr => $value) {
                $config->$attr = $value;
            }

            if ($valid) {
                $config->save();
                
                return $this->c->Redirect->page('AdminPermissions')->message('Perms updated redirect');
            }
    
            $this->fIswev  = $v->getErrors();
        }

        $this->aIndex    = 'permissions';
        $this->nameTpl   = 'admin/form';
        $this->titles    = \ForkBB\__('Permissions');
        $this->form      = $this->viewForm($config);
        $this->titleForm = \ForkBB\__('Permissions head');
        $this->classForm = 'editpermissions';

        return $this;
    }
    
    /**
     * Формирует данные для формы
     *
     * @param Config $config
     * 
     * @return array
     */
    protected function viewForm(Config $config)
    {
        $form = [
            'action' => $this->c->Router->link('AdminPermissions'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminPermissions'),
            ],
            'sets'   => [],
            'btns'   => [
                'update'  => [
                    'type'      => 'submit',
                    'value'     => \ForkBB\__('Update'),
                    'accesskey' => 'u',
                ],
            ],
        ];

        $yn     = [1 => \ForkBB\__('Yes'), 0 => \ForkBB\__('No')];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Posting subhead'),
            'fields' => [
                'p_message_bbcode' => [
                    'type'   => 'radio',
                    'value'  => $config->p_message_bbcode,
                    'values' => $yn,
                    'title'  => \ForkBB\__('BBCode label'),
                    'info'   => \ForkBB\__('BBCode help'),
                ],
                'p_message_img_tag' => [
                    'type'   => 'radio',
                    'value'  => $config->p_message_img_tag,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Image tag label'),
                    'info'   => \ForkBB\__('Image tag help'),
                ],
                'p_message_all_caps' => [
                    'type'   => 'radio',
                    'value'  => $config->p_message_all_caps,
                    'values' => $yn,
                    'title'  => \ForkBB\__('All caps message label'),
                    'info'   => \ForkBB\__('All caps message help'),
                ],
                'p_subject_all_caps' => [
                    'type'   => 'radio',
                    'value'  => $config->p_subject_all_caps,
                    'values' => $yn,
                    'title'  => \ForkBB\__('All caps subject label'),
                    'info'   => \ForkBB\__('All caps subject help'),
                ],
                'p_force_guest_email' => [
                    'type'   => 'radio',
                    'value'  => $config->p_force_guest_email,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Require e-mail label'),
                    'info'   => \ForkBB\__('Require e-mail help'),
                ],
            ],
        ];

        $form['sets'][] = [
            'legend' => \ForkBB\__('Signatures subhead'),
            'fields' => [
                'p_sig_bbcode' => [
                    'type'   => 'radio',
                    'value'  => $config->p_sig_bbcode,
                    'values' => $yn,
                    'title'  => \ForkBB\__('BBCode sigs label'),
                    'info'   => \ForkBB\__('BBCode sigs help'),
                ],
                'p_sig_img_tag' => [
                    'type'   => 'radio',
                    'value'  => $config->p_sig_img_tag,
                    'values' => $yn,
                    'title'  => \ForkBB\__('Image tag sigs label'),
                    'info'   => \ForkBB\__('Image tag sigs help'),
                ],
                'p_sig_all_caps' => [
                    'type'   => 'radio',
                    'value'  => $config->p_sig_all_caps,
                    'values' => $yn,
                    'title'  => \ForkBB\__('All caps sigs label'),
                    'info'   => \ForkBB\__('All caps sigs help'),
                ],
                'p_sig_length' => [
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 16000,
                    'value' => $config->p_sig_length,
                    'title' => \ForkBB\__('Max sig length label'),
                    'info'  => \ForkBB\__('Max sig length help'),
                ],
                'p_sig_lines' => [
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 100,
                    'value' => $config->p_sig_lines,
                    'title' => \ForkBB\__('Max sig lines label'),
                    'info'  => \ForkBB\__('Max sig lines help'),
                ],

            ],
        ];

        return $form;
    }
}
