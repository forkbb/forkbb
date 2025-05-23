<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin\Parser;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Parser;
use ForkBB\Models\Config\Config;
use function \ForkBB\__;

class Smilies extends Parser
{
    /**
     * Паттерн для имени изображения
     */
    protected string $pattern = '%^[a-z0-9-_]+\.(?:gif|jpe?g|png|webp)$%isD';

    /**
     * Паттерн для доступных к загрузке типов файлов
     */
    protected string $accept = 'image/*';

    /**
    * Заполняет список файлов из каталога смайлов
    */
    protected function calcImages(): void
    {
        $dir    = $this->c->DIR_PUBLIC . '/img/sm/';
        $result = [];

        if (
            \is_dir($dir)
            && false !== ($dh = \opendir($dir))
        ) {
            while (false !== ($entry = \readdir($dh))) {
                if (
                    \preg_match($this->pattern, $entry)
                    && \is_file($dir . $entry)
                ) {
                    $result[] = $entry;
                }
            }

            \closedir($dh);
            \sort($result, \SORT_NATURAL);
        }

        $this->imageList = $result;
    }

    /**
     * Управление смайлами
     */
    public function view(array $args, string $method): Page
    {
        $this->calcImages();

        if ('POST' === $method) {
            $imageStr = \implode(',', $this->imageList);
            $v        = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'                 => 'token:AdminSmilies',
                    'smilies.*.sm_code'     => 'required|string:trim|max:20',
                    'smilies.*.sm_position' => 'required|integer|min:0|max:9999',
                    'smilies.*.sm_image'    => 'required|string|max:40|in:' . $imageStr,
                    'new_sm_code'           => 'string:trim|max:20',
                    'new_sm_position'       => 'integer|min:0|max:9999',
                    'new_sm_image'          => 'string|max:40|in:' . $imageStr,
                ])->addAliases([
                    'smilies.*.sm_code'     => 'Smiley code label',
                    'smilies.*.sm_position' => 'Position label',
                    'smilies.*.sm_image'    => 'Name label',
                    'new_sm_code'           => 'Smiley code label',
                    'new_sm_position'       => 'Position label',
                    'new_sm_image'          => 'Name label',
                ])->addArguments([
                ])->addMessages([
                ]);

            $valid = $v->validation($_POST);
            $data  = $v->getData();

            if ($valid) {
                $old = $this->c->smilies->list;
                $new = $v->smilies;

                foreach ($new as $id => $cur) {
                    if (isset($old[$id]) && $cur != $old[$id]) {
                        $this->c->smilies->update($id, $cur);
                    }
                }

                if ('' != $v->new_sm_code) {
                    $this->c->smilies->insert([
                        'sm_code'     => $v->new_sm_code,
                        'sm_position' => $v->new_sm_position,
                        'sm_image'    => $v->new_sm_image,
                    ]);
                }

                $this->c->smilies->reset();

                return $this->c->Redirect->page('AdminSmilies')->message('Smilies updated redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();

        } else {
            $data = [];
        }

        $this->nameTpl         = 'admin/smilies';
        $this->aCrumbs[]       = [$this->c->Router->link('AdminSmilies'), 'Smilies management'];
        $this->formSmilies     = $this->formSmilies($data);
        $this->formImages      = $this->formImages();
        $this->formUploadImage = $this->formUploadImage();

        return $this;
    }

    /**
     * Формирует данные для формы смайлов
     */
    protected function formSmilies(array $data): array
    {
        $form = [
            'action' => $this->c->Router->link('AdminSmilies'),
            'hidden' => [
                'token' => $this->c->Csrf->create('AdminSmilies'),
            ],
            'sets' => [
                'smilies-legend' => [
                    'class'  => ['smilies-legend'],
                    'legend' => 'Smilies list subhead',
                    'fields' => [],
                ],
            ],
            'btns'   => [
                'save' => [
                    'type'  => 'submit',
                    'value' => __('Save changes'),
                ],
            ],
        ];

        $imageList = \array_combine($this->imageList, $this->imageList);

        $i   = 1;
        $max = 0;

        foreach ($this->c->smilies->list as $id => $cur) {
            $fields = [];
            $max    = \max($max, $cur['sm_position']);

            $fields["smilies[{$id}][sm_code]"] = [
                'class'     => ['code', 'smile'],
                'type'      => 'text',
                'maxlength' => '20',
                'value'     => $data['smilies'][$id]['sm_code'] ?? $cur['sm_code'],
                'caption'   => 'Smiley code label',
                'required'  => true,
            ];
            $fields["smilies[{$id}][sm_position]"] = [
                'class'     => ['position', 'smile'],
                'type'      => 'number',
                'min'       => '0',
                'max'       => '9999',
                'value'     => $data['smilies'][$id]['sm_position'] ?? $cur['sm_position'],
                'caption'   => 'Position label',
                'required'  => true,
            ];
            $fields["smilies[{$id}][sm_image]"] = [
                'class'     => ['image', 'smile'],
                'type'     => 'select',
                'options'  => $imageList,
                'value'    => $data['smilies'][$id]['sm_image'] ?? $cur['sm_image'],
                'caption'  => 'Name label',
            ];
            $fields["smile{$id}-pic"] = [
                'class'     => ['pic', 'smile'],
                'type'      => 'str',
                'value'     => __(['<img src="%1$s" alt="%2$s">', $this->c->PUBLIC_URL . '/img/sm/' . $cur['sm_image'], $cur['sm_image']]),
                'caption'   => 'Picture label',
                'html'      => true,
            ];
            $fields["smile{$id}-del"] = [
                'class'     => ['delete', 'smile'],
                'type'      => 'btn',
                'value'     => '❌',
                'caption'   => 'Delete',
                'title'     => __('Delete'),
                'href'      => $this->c->Router->link(
                    'AdminSmiliesDelete',
                    [
                        'name' => $id,
                    ]
                ),
            ];

            $form['sets']["smile{$id}"] = [
                'class'  => ['smile'],
                'legend' => ['Smiley number %s', $i],
                'fields' => $fields,
            ];

            ++$i;
        }

        $form['sets']['new-smile'] = [
            'class'  => ['new-smile'],
            'legend' => 'New smile subhead',
            'fields' => [
                'new_sm_code' => [
                    'class'     => ['code', 'new-smile'],
                    'type'      => 'text',
                    'maxlength' => '20',
                    'value'     => $data['new_sm_code'] ?? '',
                    'caption'   => 'Smiley code label',
                ],
                'new_sm_position' => [
                    'class'     => ['position', 'new-smile'],
                    'type'      => 'number',
                    'min'       => '0',
                    'max'       => '9999',
                    'value'     => $data['new_sm_position'] ?? $max + 1,
                    'caption'   => 'Position label',
                ],
                'new_sm_image' => [
                    'class'     => ['image', 'new-smile'],
                    'type'     => 'select',
                    'options'  => $imageList,
                    'value'    => $data['new_image'] ?? null,
                    'caption'  => 'Name label',
                ],
            ],
        ];


        return $form;
    }

    /**
     * Формирует данные для формы изображений
     */
    protected function formImages(): array
    {
        $form = [
            'sets' => [
                'image-legend' => [
                    'class'  => ['image-legend'],
                    'legend' => 'Available images subhead',
                    'fields' => [],
                ],
            ],
        ];

        foreach ($this->imageList as $key => $name) {
            $fields = [];
            $fields["image{$key}-pic"] = [
                'class'     => ['pic', 'image'],
                'type'      => 'str',
                'value'     => __(['<img src="%1$s" alt="%2$s">', $this->c->PUBLIC_URL . '/img/sm/' . $name, $name]),
                'caption'   => 'Picture label',
                'html'      => true,
            ];
            $fields["image{$key}-name"] = [
                'class'   => ['name', 'image'],
                'type'    => 'str',
                'value'   => $name,
                'caption' => 'Name label',
            ];
            $fields["image{$key}-del"] = [
                'class'   => $this->fileIsBusy($name) ? ['delete', 'image', 'disabled'] : ['delete', 'image'],
                'type'    => 'link',
                'value'   => '❌',
                'caption' => 'Delete',
                'title'   => __('Delete'),
                'href'    => $this->c->Router->link(
                    'AdminSmiliesDelete',
                    [
                        'name' => $name,
                    ]
                ),
            ];

            $form['sets']["image{$key}"] = [
                'class'  => ['image'],
                'legend' => $name,
                'fields' => $fields,
            ];
        }

        return $form;
    }

    /**
     * Формирует данные для формы загрузки картинки
     */
    protected function formUploadImage(): array
    {
        $form = [
            'action'  => $this->c->Router->link('AdminSmiliesUpload'),
            'enctype' => 'multipart/form-data',
            'maxfsz'  => $this->c->Files->maxImgSize(),
            'hidden'  => [
                'token'         => $this->c->Csrf->create('AdminSmiliesUpload'),
            ],
            'sets'    => [
                'upload' => [
                    'class'  => ['upload_smile'],
                    'legend' => 'Upload image subhead',
                    'fields' => [
                        'upload_image' => [
                            'type'    => 'file',
                            'caption' => 'Upload image label',
                            'help'    => 'Upload image info',
                            'accept'  => $this->accept,
                        ],
                    ],

                ],
            ],
            'btns'    => [
                'upload' => [
                    'type'  => 'submit',
                    'value' => __('Upload'),
                ],
            ],
        ];

        return $form;
    }

    /**
     * Проверят используется ли данный файл в смайлах
     */
    protected function fileIsBusy(string $name): bool
    {
        foreach ($this->c->smilies->list as $cur) {
            if ($name === $cur['sm_image']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Удаляет смайл или изображение
     */
    public function delete(array $args, string $method): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'AdminSmiliesDelete', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        $status = FORK_MESS_SUCC;

        if (
            \is_numeric($args['name'])
            && \is_int(0 + $args['name'])
        ) {
            $this->c->smilies->delete((int) $args['name']);

            $message = 'Smile deleted redirect';

        } elseif (\preg_match($this->pattern, $args['name'])) {
            $file = $this->c->DIR_PUBLIC . '/img/sm/' . $args['name'];

            if (
                ! $this->fileIsBusy($args['name'])
                && \is_file($file)
                && \unlink($file)
            ) {
                $message = ['File %s deleted redirect', $args['name']];

            } else {
                $message = ['File %s not deleted redirect', $args['name']];
                $status  = FORK_MESS_ERR;
            }

        } else {
            return $this->c->Message->message('Bad request');
        }

        return $this->c->Redirect->page('AdminSmilies')->message($message, $status);
    }

    /**
     * Загружает изображение
     */
    public function upload(array $args, string $method): Page
    {
        $v = $this->c->Validator->reset()
            ->addValidators([
            ])->addRules([
                'token'        => 'token:AdminSmiliesUpload',
                'upload_image' => "required|image|max:{$this->c->Files->maxImgSize('K')}",
            ])->addAliases([
                'upload_image' => 'Upload image label',
            ])->addArguments([
            ])->addMessages([
            ]);

        if (
            $v->validation($_FILES + $_POST)
            && $v->upload_image instanceof Image
        ) {
            if (
                $v->upload_image
                    ->rename(true)
                    ->rewrite(false)
                    ->toFile($this->c->DIR_PUBLIC . '/img/sm/*.(jpg|png|gif)')
            ) {
                return $this->c->Redirect->page('AdminSmilies')->message('Image uploaded redirect', FORK_MESS_SUCC);

            } else {
                return $this->c->Message->message($v->upload_image->error());
            }
        }

        $this->fIswev = $v->getErrors();

        return $this->view([], 'GET');
    }
}
