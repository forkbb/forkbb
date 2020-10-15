<?php

declare(strict_types=1);

namespace ForkBB\Models\Pages\Admin\Parser;

use ForkBB\Core\Image;
use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\Admin\Parser;
use ForkBB\Models\Config\Model as Config;
use function \ForkBB\__;

class Smilies extends Parser
{
    /**
     * Паттерн для имени изображения
     * @var string
     */
    protected $pattern = '%^[a-z0-9-_]+\.(?:gif|jpe?g|png|webp)$%isD';

    /**
     * Паттерн для доступных к загрузке типов файлов
     * @var string
     */
    protected $accept = 'image/*';

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

                return $this->c->Redirect->page('AdminSmilies')->message('Smilies updated redirect');
            }

            $this->fIswev = $v->getErrors();
        } else {
            $data = [];
        }

        $this->nameTpl         = 'admin/smilies';
        $this->aCrumbs[]       = [
            $this->c->Router->link('AdminSmilies'),
            __('Smilies management'),
        ];
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
                    'class'  => 'smilies-legend',
                    'legend' => __('Smilies list subhead'),
                    'fields' => [],
                ],
            ],
            'btns'   => [
                'save' => [
                    'type'      => 'submit',
                    'value'     => __('Save changes'),
//                    'accesskey' => 's',
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
                'caption'   => __('Smiley code label'),
                'required'  => true,
            ];
            $fields["smilies[{$id}][sm_position]"] = [
                'class'     => ['position', 'smile'],
                'type'      => 'number',
                'min'       => '0',
                'max'       => '9999',
                'value'     => $data['smilies'][$id]['sm_position'] ?? $cur['sm_position'],
                'caption'   => __('Position label'),
                'required'  => true,
            ];
            $fields["smilies[{$id}][sm_image]"] = [
                'class'     => ['image', 'smile'],
                'type'     => 'select',
                'options'  => $imageList,
                'value'    => $data['smilies'][$id]['sm_image'] ?? $cur['sm_image'],
                'caption'  => __('Name label'),
            ];
            $fields["smile{$id}-pic"] = [
                'class'     => ['pic', 'smile'],
                'type'      => 'str',
                'value'     => __('<img src="%1$s" alt="%2$s">', $this->c->PUBLIC_URL . '/img/sm/' . $cur['sm_image'], $cur['sm_image']),
                'caption'   => __('Picture label'),
                'html'      => true,
            ];
            $fields["smile{$id}-del"] = [
                'class'     => ['delete', 'smile'],
                'type'      => 'btn',
                'value'     => '❌',
                'caption'   => __('Delete'),
                'title'     => __('Delete'),
                'link'      => $this->c->Router->link(
                    'AdminSmiliesDelete',
                    [
                        'name'  => $id,
                        'token' => null,
                    ]
                ),
            ];

            $form['sets']["smile{$id}"] = [
                'class'  => 'smile',
                'legend' => __('Smiley number %s', $i),
                'fields' => $fields,
            ];

            ++$i;
        }

        $form['sets']['new-smile'] = [
            'class'  => 'new-smile',
            'legend' => __('New smile subhead'),
            'fields' => [
                'new_sm_code' => [
                    'class'     => ['code', 'new-smile'],
                    'type'      => 'text',
                    'maxlength' => '20',
                    'value'     => $data['new_sm_code'] ?? '',
                    'caption'   => __('Smiley code label'),
                ],
                'new_sm_position' => [
                    'class'     => ['position', 'new-smile'],
                    'type'      => 'number',
                    'min'       => '0',
                    'max'       => '9999',
                    'value'     => $data['new_sm_position'] ?? $max + 1,
                    'caption'   => __('Position label'),
                ],
                'new_sm_image' => [
                    'class'     => ['image', 'new-smile'],
                    'type'     => 'select',
                    'options'  => $imageList,
                    'value'    => $data['new_image'] ?? null,
                    'caption'  => __('Name label'),
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
                    'class'  => 'image-legend',
                    'legend' => __('Available images subhead'),
                    'fields' => [],
                ],
            ],
        ];

        foreach ($this->imageList as $key => $name) {
            $fields = [];
            $fields["image{$key}-pic"] = [
                'class'     => ['pic', 'image'],
                'type'      => 'str',
                'value'     => __('<img src="%1$s" alt="%2$s">', $this->c->PUBLIC_URL . '/img/sm/' . $name, $name),
                'caption'   => __('Picture label'),
                'html'      => true,
            ];
            $fields["image{$key}-name"] = [
                'class'   => ['name', 'image'],
                'type'    => 'str',
                'value'   => $name,
                'caption' => __('Name label'),
            ];
            $fields["image{$key}-del"] = [
                'class'   => ['delete', 'image'],
                'type'    => 'link',
                'value'   => '❌',
                'caption' => __('Delete'),
                'title'   => __('Delete'),
                'href'    => $this->c->Router->link(
                    'AdminSmiliesDelete',
                    [
                        'name'  => $name,
                        'token' => null,
                    ]
                ),
            ];

            $form['sets']["image{$key}"] = [
                'class'  => 'image',
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
            'hidden'  => [
                'token'         => $this->c->Csrf->create('AdminSmiliesUpload'),
                'MAX_FILE_SIZE' => $this->c->Files->maxImgSize(),
            ],
            'sets'    => [
                'upload' => [
                    'class'  => 'upload_smile',
                    'legend' => __('Upload image subhead'),
                    'fields' => [
                        'upload_image' => [
                            'type'    => 'file',
                            'caption' => __('Upload image label'),
                            'info'    => __('Upload image info'),
                            'accept'  => $this->accept,
                        ],
                    ],

                ],
            ],
            'btns'    => [
                'upload' => [
                    'type'      => 'submit',
                    'value'     => __('Upload'),
//                    'accesskey' => 's',
                ],
            ],
        ];

        return $form;
    }

    /**
     * Удаляет смайл или изображение
     */
    public function delete(array $args, string $method): Page
    {
        if (! $this->c->Csrf->verify($args['token'], 'AdminSmiliesDelete', $args)) {
            return $this->c->Message->message($this->c->Csrf->getError());
        }

        if (
            \is_numeric($args['name'])
            && \is_int(0 + $args['name'])
        ) {
            $this->c->smilies->delete((int) $args['name']);

            $message = 'Smile deleted redirect';
        } elseif (\preg_match($this->pattern, $args['name'])) {
            $file = $this->c->DIR_PUBLIC . '/img/sm/' . $args['name'];

            if (
                \is_file($file)
                && @\unlink($file)
            ) {
                $message = __('File %s deleted redirect', $args['name']);
            } else {
                $message = __('File %s not deleted redirect', $args['name']);
            }
        } else {
            return $this->c->Message->message('Bad request');
        }

        return $this->c->Redirect->page('AdminSmilies')->message($message);
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
                return $this->c->Redirect->page('AdminSmilies')->message('Image uploaded redirect');
            } else {
                return $this->c->Message->message($v->upload_image->error());
            }
        }

        $this->fIswev = $v->getErrors();

        return $this->view([], 'GET');
    }
}
