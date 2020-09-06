<?php

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Config\Model as Config;
use function \ForkBB\__;

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
    public function edit(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_permissions');

        $config = clone $this->c->config;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'               => 'token:AdminPermissions',
                    'p_message_bbcode'    => 'required|integer|in:0,1',
                    'p_message_img_tag'   => 'required|integer|in:0,1',
                    'p_sig_bbcode'        => 'required|integer|in:0,1',
                    'p_sig_img_tag'       => 'required|integer|in:0,1',
                    'o_smilies'               => 'required|integer|in:0,1',
                    'o_smilies_sig'           => 'required|integer|in:0,1',
                    'o_make_links'            => 'required|integer|in:0,1',
                    'o_quote_depth'           => 'required|integer|min:0|max:9',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
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
        $this->form      = $this->formEdit($config);
        $this->titleForm = __('Permissions head');
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
    protected function formEdit(Config $config): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminPermissions'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminPermissions'),
            ],
            'sets'   => [],
            'btns'   => [
                'save' => [
                    'type'      => 'submit',
                    'value'     => __('Save changes'),
//                    'accesskey' => 's',
                ],
            ],
        ];

        $yn = [1 => __('Yes'), 0 => __('No')];

        $form['sets']['posting'] = [
            'legend' => __('Posting subhead'),
            'fields' => [
                'p_message_bbcode' => [
                    'type'    => 'radio',
                    'value'   => $config->p_message_bbcode,
                    'values'  => $yn,
                    'caption' => __('BBCode label'),
                    'info'    => __('BBCode help'),
                ],
                'p_message_img_tag' => [
                    'type'    => 'radio',
                    'value'   => $config->p_message_img_tag,
                    'values'  => $yn,
                    'caption' => __('Image tag label'),
                    'info'    => __('Image tag help'),
                ],
                'o_smilies' => [
                    'type'    => 'radio',
                    'value'   => $config->o_smilies,
                    'values'  => $yn,
                    'caption' => __('Smilies label'),
                    'info'    => __('Smilies help'),
                ],
                'o_make_links' => [
                    'type'    => 'radio',
                    'value'   => $config->o_make_links,
                    'values'  => $yn,
                    'caption' => __('Clickable links label'),
                    'info'    => __('Clickable links help'),
                ],
                'o_quote_depth' => [
                    'type'    => 'number',
                    'min'     => 0,
                    'max'     => 9,
                    'value'   => $config->o_quote_depth,
                    'caption' => __('Quote depth label'),
                    'info'    => __('Quote depth help'),
                ],
            ],
        ];

        $form['sets']['signatures'] = [
            'legend' => __('Signatures subhead'),
            'fields' => [
                'p_sig_bbcode' => [
                    'type'    => 'radio',
                    'value'   => $config->p_sig_bbcode,
                    'values'  => $yn,
                    'caption' => __('BBCode sigs label'),
                    'info'    => __('BBCode sigs help'),
                ],
                'p_sig_img_tag' => [
                    'type'    => 'radio',
                    'value'   => $config->p_sig_img_tag,
                    'values'  => $yn,
                    'caption' => __('Image tag sigs label'),
                    'info'    => __('Image tag sigs help'),
                ],
                'o_smilies_sig' => [
                    'type'    => 'radio',
                    'value'   => $config->o_smilies_sig,
                    'values'  => $yn,
                    'caption' => __('Smilies sigs label'),
                    'info'    => __('Smilies sigs help'),
                ],

            ],
        ];

        return $form;
    }
}
