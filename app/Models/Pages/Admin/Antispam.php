<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Config\Config;
use function \ForkBB\__;

class Antispam extends Admin
{
    /**
     * Редактирование натроек форума
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_antispam');

        $config = clone $this->c->config;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'           => 'token:AdminAntispam',
                    'b_ant_hidden_ch' => 'required|integer|in:0,1',
                    'b_ant_use_js'    => 'required|integer|in:0,1',
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

                return $this->c->Redirect->page('AdminAntispam')->message('Settings updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev  = $v->getErrors();
        }

        $this->aIndex    = 'antispam';
        $this->nameTpl   = 'admin/form';
        $this->form      = $this->form($config);
        $this->titleForm = 'Antispam head';
        $this->classForm = ['antispam'];

        return $this;
    }

    /**
     * Формирует данные для формы
     */
    protected function form(Config $config): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminAntispam'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminAntispam'),
            ],
            'sets'   => [],
            'btns'   => [
                'save'  => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        $yn     = [1 => __('Yes'), 0 => __('No')];
        $langs  = $this->c->Func->getNameLangs();
        $styles = $this->c->Func->getStyles();

        $form['sets']['general'] = [
            'legend' => 'General subhead',
            'fields' => [
                'b_ant_hidden_ch' => [
                    'type'    => 'radio',
                    'value'   => $config->b_ant_hidden_ch,
                    'values'  => $yn,
                    'caption' => 'Hidden checkboxes label',
                    'help'    => 'Hidden checkboxes help',
                ],
                'b_ant_use_js' => [
                    'type'    => 'radio',
                    'value'   => $config->b_ant_use_js,
                    'values'  => $yn,
                    'caption' => 'Use javascript label',
                    'help'    => 'Use javascript help',
                ],
            ],
        ];

        return $form;
    }
}
