<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
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
use RuntimeException;

class Uploads extends Admin
{
    /**
     * Обслуживание
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('validator');
        $this->c->Lang->load('admin_uploads');

        $config = clone $this->c->config;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'                => 'token:AdminUploads',
                    'b_upload'             => 'required|integer|in:0,1',
                    'i_upload_img_quality' => 'required|integer|min:0|max:100',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $this->c->config->b_upload             = $v->b_upload;
                $this->c->config->i_upload_img_quality = $v->i_upload_img_quality;
                $this->c->config->save();

                return $this->c->Redirect->page('AdminUploads')->message('Data updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->nameTpl         = 'admin/uploads';
        $this->aIndex          = 'uploads';
        $this->formUploads     = $this->formUploads($config);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formUploads(Config $config): array
    {
        return [
            'action' => $this->c->Router->link('AdminUploads'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUploads'),
            ],
            'sets'   => [
                'maint' => [
                    'legend' => 'Uploads head',
                    'fields' => [
                        'b_upload' => [
                            'type'    => 'radio',
                            'value'   => $config->b_upload,
                            'values'  => [1 => __('Yes'), 0 => __('No')],
                            'caption' => 'Uploads mode label',
                            'help'    => ['Uploads mode help', __('User groups'), $this->c->Router->link('AdminGroups')],
                        ],
                        'i_upload_img_quality' => [
                            'type'    => 'number',
                            'min'     => '0',
                            'max'     => '100',
                            'value'   => $config->i_upload_img_quality,
                            'caption' => 'Upload quality label',
                            'help'    => 'Upload quality help',
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'submit' => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];
    }
}
