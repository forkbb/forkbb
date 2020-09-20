<?php

namespace ForkBB\Models\Pages\Admin\Parser;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Parser;
use ForkBB\Models\Config\Model as Config;
use function \ForkBB\__;

class Edit extends Parser
{
    /**
     * Редактирование натроек парсера
     */
    public function edit(array $args, string $method): Page
    {
        $config = clone $this->c->config;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'               => 'token:AdminParser',
                    'p_message_bbcode'    => 'required|integer|in:0,1',
                    'p_message_img_tag'   => 'required|integer|in:0,1',
                    'p_sig_bbcode'        => 'required|integer|in:0,1',
                    'p_sig_img_tag'       => 'required|integer|in:0,1',
                    'o_smilies'           => 'required|integer|in:0,1',
                    'o_smilies_sig'       => 'required|integer|in:0,1',
                    'o_make_links'        => 'required|integer|in:0,1',
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

                return $this->c->Redirect->page('AdminParser')->message('Parser settings updated redirect');
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->nameTpl   = 'admin/form';
        $this->form      = $this->formEdit($config);
        $this->titleForm = __('Parser settings head');
        $this->classForm = 'parser-settings';

        return $this;
    }

    /**
     * Формирует данные для формы
     */
    protected function formEdit(Config $config): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminParser'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminParser'),
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
            'legend' => __('BBCode subhead'),
            'fields' => [
                'p_message_bbcode' => [
                    'type'    => 'radio',
                    'value'   => $config->p_message_bbcode,
                    'values'  => $yn,
                    'caption' => __('BBCode label'),
                    'info'    => __('BBCode help'),
                ],
                'p_sig_bbcode' => [
                    'type'    => 'radio',
                    'value'   => $config->p_sig_bbcode,
                    'values'  => $yn,
                    'caption' => __('BBCode sigs label'),
                    'info'    => __('BBCode sigs help'),
                ],
                'p_message_img_tag' => [
                    'type'    => 'radio',
                    'value'   => $config->p_message_img_tag,
                    'values'  => $yn,
                    'caption' => __('Image tag label'),
                    'info'    => __('Image tag help'),
                ],
                'p_sig_img_tag' => [
                    'type'    => 'radio',
                    'value'   => $config->p_sig_img_tag,
                    'values'  => $yn,
                    'caption' => __('Image tag sigs label'),
                    'info'    => __('Image tag sigs help'),
                ],
                'o_make_links' => [
                    'type'    => 'radio',
                    'value'   => $config->o_make_links,
                    'values'  => $yn,
                    'caption' => __('Clickable links label'),
                    'info'    => __('Clickable links help'),
                ],
                'bbcode_management' => [
                    'type'    => 'btn',
                    'caption' => null,
                    'value'   => __('BBCode management'),
                    'title'   => __('BBCode management'),
                    'link'    => $this->c->Router->link('AdminBBCode'),
                ],
            ],
        ];

        $form['sets']['signatures'] = [
            'legend' => __('Smilies subhead'),
            'fields' => [
                'o_smilies' => [
                    'type'    => 'radio',
                    'value'   => $config->o_smilies,
                    'values'  => $yn,
                    'caption' => __('Smilies mess label'),
                    'info'    => __('Smilies mess help'),
                ],
                'o_smilies_sig' => [
                    'type'    => 'radio',
                    'value'   => $config->o_smilies_sig,
                    'values'  => $yn,
                    'caption' => __('Smilies sigs label'),
                    'info'    => __('Smilies sigs help'),
                ],
                'smilies_management' => [
                    'type'    => 'btn',
                    'caption' => null,
                    'value'   => __('Smilies management'),
                    'title'   => __('Smilies management'),
                    'link'    => $this->c->Router->link('AdminSmilies'),
                ],

            ],
        ];

        return $form;
    }
}
