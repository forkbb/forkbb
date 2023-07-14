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
use ForkBB\Models\Attachment\Attachments;
use ForkBB\Models\Pages\Admin;
use ForkBB\Models\Config\Config;
use function \ForkBB\{__, dt, size};
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
                    'ext_check' => [$this, 'vExtsCheck'],
                ])->addRules([
                    'token'                   => 'token:AdminUploads',
                    'b_upload'                => 'required|integer|in:0,1',
                    's_upload_img_outf'       => 'required|string:trim|max:255|ext_check',
                    'i_upload_img_quality'    => 'required|integer|min:0|max:100',
                    'i_upload_img_axis_limit' => 'required|integer|min:100|max:20000',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                ]);

            if ($v->validation($_POST)) {
                $data = $v->getData(false, ['token']);

                foreach ($data as $attr => $value) {
                    $this->c->config->$attr = $value;
                }

                $this->c->config->save();

                return $this->c->Redirect->page('AdminUploads')->message('Data updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
        }

        $this->nameTpl     = 'admin/uploads';
        $this->aIndex      = 'uploads';
        $this->formUploads = $this->formUploads($config);

        $attachments       = $this->c->attachments;
        $attachments->page = $args['page'] ?: 1;
        $this->pagination  = $attachments->pagination;

        if ($attachments->hasPage()) {
            $this->formFileList = $this->formFileList($attachments, $args);
        } else {
            $this->badPage = $attachments->page;
        }

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
                        's_upload_img_outf' => [
                            'required'  => true,
                            'type'      => 'text',
                            'maxlength' => '255',
                            'value'     => $config->s_upload_img_outf,
                            'caption'   => 'Output image types label',
                            'help'      => 'Output image types help',
                        ],
                        'i_upload_img_quality' => [
                            'type'    => 'number',
                            'min'     => '0',
                            'max'     => '100',
                            'value'   => $config->i_upload_img_quality,
                            'caption' => 'Upload quality label',
                            'help'    => 'Upload quality help',
                        ],
                        'i_upload_img_axis_limit' => [
                            'type'    => 'number',
                            'min'     => '100',
                            'max'     => '20000',
                            'value'   => $config->i_upload_img_axis_limit,
                            'caption' => 'Upload axis limit label',
                            'help'    => 'Upload axis limit help',
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

    /**
     * Наводит порядок в расширениях
     */
    public function vExtsCheck(Validator $v, string $exts): string
    {
        $allowed = [
            'webp' => true,
            'jpg'  => true,
            'jpeg' => 'jpg',
            'png'  => true,
            'gif'  => true,
            'avif' => true,
        ];

        $exts   = \explode(',', \mb_strtolower($exts, 'UTF-8'));
        $result = [];

        foreach ($exts as $ext) {
            $ext = \trim($ext);

            if (isset($allowed[$ext])) {
                if (\is_string($allowed[$ext])) {
                    $ext = $allowed[$ext];
                }

                $result[$ext] = $ext;
            }
        }

        if (empty($result)) {
            return 'webp';
        } else {
            return \implode(',', $result);
        }
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formFileList(Attachments $attachments, array $args): array
    {
        $data = $attachments->pageData();
        $uIds = [];

        foreach ($attachments->idsList as $id) {
            if (isset($data[$id])) {
                $uid        = $data[$id]['uid'];
                $uIds[$uid] = $uid;
            }
        }

        $users = $this->c->users->loadByIds($uIds);

        $form = [/*
            'action' => $this->c->Router->link('AdminUploads', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminUploads', $args),
            ],*/
            'sets'   => [],
            /*'btns'   => [],*/
        ];

        $ids = $attachments->idsList;

        \array_unshift($ids, 0);

        foreach ($ids as $id) {
            $att    = $data[$id] ?? null;
            $user   = isset($att['uid'], $users[$att['uid']]) ? $users[$att['uid']] : null;
            $fields = [];

            $fields["f{$id}-wrap"] = [
                'class' => ['main-wrap'],
                'type'  => 'wrap',
            ];
            $y = isset($att['path']);
            $fields["f{$id}-file"] = [
                'class'   => ['filelist', 'file'],
                'type'    => $y ? 'include' : 'str',
                'caption' => 'File head',
                'value'   => $y ? \basename($att['path']) : '',
                'title'   => $y ? $att['path'] : '',
                'href'    => $y ? $this->c->PUBLIC_URL . $attachments::FOLDER . $att['path'] : '',
                'include' => 'admin/uploads_file',
            ];
            $fields["f{$id}-size"] = [
                'class'   => ['filelist', 'size'],
                'type'    => 'str',
                'caption' => 'Size head',
                'value'   => isset($att['size_kb']) ? size(1024 * ($att['size_kb'] ?: 1)) : '',
            ];
            $y = isset($att['created']);
            $fields["f{$id}-created"] = [
                'class'   => ['filelist', 'created'],
                'type'    => $y ? 'link' : 'str',
                'caption' => 'Created head',
                'value'   => $y ? dt($att['created']) : '',
                'title'   => $y ? $att['uip'] : '',
                'href'    => $y ? $this->c->Router->link('AdminHost', ['ip' => $att['uip']]) : '',
            ];

            if ($user) {
                $fields["f{$id}-user"] = [
                    'class'   => ['filelist', 'user'],
                    'type'    => 'link',
                    'caption' => 'User head',
                    'value'   => $user->username,
                    'href'    => $this->c->Router->link('User', ['id' => $user->id, 'name' => $user->username]),
                ];
            } else {
                $fields["f{$id}-user"] = [
                    'class'   => ['filelist', 'user'],
                    'type'    => 'str',
                    'caption' => 'User head',
                    'value'   => $id ? 'User #' . ($att['uid'] ?: '??') : '',
                ];
            }
            $fields[] = [
                'type' => 'endwrap',
            ];
            $fields["f{$id}-action"] = [
                'class'   => ['action'],
                'caption' => 'Action',
                'type'    => 'str',
                'value'   => $id ? 'X' : '',
            ];


            $form['sets']["f{$id}"] = [
                'class'  => ['filelist'],
                'legend' => (string) $id,
                'fields' => $fields,
            ];
        }

        return $form;
    }
}
