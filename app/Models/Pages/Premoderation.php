<?php
/**
 * This file is part of the ForkBB <https://forkbb.ru, https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Draft\Draft;
use ForkBB\Models\Page;
use function \ForkBB\__;

class Premoderation extends Page
{
    /**
     * Отображает очередь премодерации
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('draft');

        $premod = $this->c->premod->init();

        if ('POST' === $method) {
            exit("<pre>\n" . \print_r($_POST, true));
        }

        if ($premod->count < 1) {
            return $this->c->Message->message('Pre-moderation queue is empty', true, 199);
        }

        $this->numPage = $args['page'] ?? 1;
        $this->drafts  = $premod->view($this->numPage);

        if (empty($this->drafts)) {
            return $this->c->Message->message('Page missing', false, 404);
        }

        $this->numPages   = $premod->numPages();
        $this->pagination = $this->c->Func->paginate($this->numPages, $this->numPage, 'Premoderation');
        $this->useMediaJS = true;
        $this->nameTpl    = 'premod';
        $this->identifier = 'search-result';
        $this->fIndex     = self::FI_PREMOD;
        $this->onlinePos  = 'premod';
        $this->robots     = 'noindex';
        $this->crumbs     = $this->crumbs([$this->c->Router->link('Premoderation'), 'Pre-moderation']);
        $this->formAction = $this->formAction();

        $this->c->Parser; // предзагрузка

        $this->c->Lang->load('search');

        return $this;
    }


    /**
     * Создает массив данных для формы
     */
    protected function formAction(): array
    {
        return [
            'id'     => 'id-form-action',
            'action' => $this->c->Router->link('Premoderation'),
            'hidden' => [
                'token' => $this->c->Csrf->create('Premoderation'),
                'page'  => $this->numPage,
            ],
            'sets'   => [
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => 'Confirm action',
                            'checked' => false,
                        ],
                    ],
                ],

            ],
            'btns'   => [
                'execute' => [
                    'type'  => 'submit',
                    'value' => __('Execute'),
                ],
            ],
        ];
    }
}
