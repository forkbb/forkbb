<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin\Parser;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Parser;
use ForkBB\Models\Config\Config;
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
                    'p_sig_bbcode'        => 'required|integer|in:0,1',
                    'b_smilies'           => 'required|integer|in:0,1',
                    'b_smilies_sig'       => 'required|integer|in:0,1',
                    'b_make_links'        => 'required|integer|in:0,1',
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
        $this->titleForm = 'Parser settings head';
        $this->classForm = ['parser-settings'];

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
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        $yn = [1 => __('Yes'), 0 => __('No')];

        $form['sets']['bbcode'] = [
            'legend' => 'BBCode subhead',
            'fields' => [
                'p_message_bbcode' => [
                    'type'    => 'radio',
                    'value'   => $config->p_message_bbcode,
                    'values'  => $yn,
                    'caption' => 'BBCode label',
                    'help'    => 'BBCode help',
                ],
                'p_sig_bbcode' => [
                    'type'    => 'radio',
                    'value'   => $config->p_sig_bbcode,
                    'values'  => $yn,
                    'caption' => 'BBCode sigs label',
                    'help'    => 'BBCode sigs help',
                ],
                'b_make_links' => [
                    'type'    => 'radio',
                    'value'   => $config->b_make_links,
                    'values'  => $yn,
                    'caption' => 'Clickable links label',
                    'help'    => 'Clickable links help',
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

        $form['sets']['smilies'] = [
            'legend' => 'Smilies subhead',
            'fields' => [
                'b_smilies' => [
                    'type'    => 'radio',
                    'value'   => $config->b_smilies,
                    'values'  => $yn,
                    'caption' => 'Smilies mess label',
                    'help'    => 'Smilies mess help',
                ],
                'b_smilies_sig' => [
                    'type'    => 'radio',
                    'value'   => $config->b_smilies_sig,
                    'values'  => $yn,
                    'caption' => 'Smilies sigs label',
                    'help'    => 'Smilies sigs help',
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
