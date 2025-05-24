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

        if ($premod->count < 1) {
            return $this->c->Message->message('Pre-moderation queue is empty', false, 199);
        }

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                ])->addRules([
                    'token'   => 'token:Premoderation',
                    'page'    => 'integer|min:1|max:9999999999',
                    'draft'   => 'required|array',
                    'draft.*' => 'required|integer|in:-1,0,1',
                    'confirm' => 'required|integer|in:1',
                    'execute' => 'string',
                ])->addAliases([
                ])->addArguments([
                ])->addMessages([
                    'confirm' => 'No confirm redirect',
                ]);

            if ($v->validation($_POST)) {
                $this->actions($v->draft);

                return $this->c->Redirect->page('Premoderation', ['page' => $v->page])->message('Selected posts processed redirect', FORK_MESS_SUCC);
            }

            $this->fIswev = $v->getErrors();
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

    protected function actions(array $list): void
    {
        $forDelete  = [];
        $forPublish = [];
        $available  = \array_flip($this->c->premod->idList);

        foreach ($list as $id => $action) {
            if (
                empty($action)
                || ! isset($available[$id])
            ) {
                continue;
            }

            switch ($action) {
                case 1:
                    $forPublish[$id] = $id;
                                             // публикуемые черновики тоже удаляем
                case -1:
                    $forDelete[$id] = $id;

                    break;
            }
        }

        if (! empty($forDelete)) {
            $drafts = $this->c->drafts->loadByIds($forDelete);

            if (! empty($forPublish)) {
                //????
            }

            $this->c->drafts->delete(...$drafts);
        }
    }
}
