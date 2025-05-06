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

class Drafts extends Page
{
    /**
     * Просмотр черновиков
     */
    public function view(array $args, string $method): Page
    {
        $this->c->Lang->load('draft');

        if ($this->user->num_drafts < 1) {
            return $this->c->Message->message('No drafts');
        }

        $this->numPage = $args['page'] ?? 1;
        $this->drafts  = $this->c->drafts->view($this->numPage);

        if (empty($this->drafts)) {
            $count = $this->c->drafts->count();

            if ($count !== $this->user->num_drafts) {
                $this->user->num_drafts = $count;

                $this->c->users->update($this->user);
            }

            return $this->c->Message->message($count > 0 ? 'Page missing' : 'No drafts', false, 404);
        }

        $this->numPages   = $this->c->drafts->numPages();
        $this->pagination = $this->c->Func->paginate($this->numPages, $this->numPage, 'Drafts');
        $this->useMediaJS = true;
        $this->nameTpl    = 'drafts';
        $this->identifier = 'search-result';
        $this->fIndex     = self::FI_DRAFT;
        $this->onlinePos  = 'drafts';
        $this->robots     = 'noindex';
        $this->crumbs     = $this->crumbs([$this->c->Router->link('Drafts'), 'Drafts']);

        $this->c->Parser; // предзагрузка

        $this->c->Lang->load('search');

        return $this;
    }

    /**
     * Удаление черновика
     */
    public function delete(array $args, string $method): Page
    {
        if (
            ! $this->c->userRules->useDraft
            || ! ($draft = $this->c->drafts->load($args['did'])) instanceof Draft
            || $draft->poster_id !== $this->user->id
        ) {
            return $this->c->Message->message('Bad request');
        }

        $this->c->Lang->load('validator');
        $this->c->Lang->load('delete');
        $this->c->Lang->load('draft');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'   => 'token:DeleteDraft',
                    'confirm' => 'checkbox',
                    'delete'  => 'required|string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (
                ! $v->validation($_POST)
                || '1' !== $v->confirm
            ) {
                return $this->c->Redirect->page('Drafts')->message('No confirm redirect', FORK_MESS_WARN);

            } else {
                $this->c->drafts->delete($draft);

                return $this->c->Redirect->page('Drafts')->message('Draft del redirect', FORK_MESS_SUCC);
            }
        }

        $draft->__posted = \time();

        $this->c->Parser; // предзагрузка

        $this->identifier = 'delete';
        $this->nameTpl    = 'post';
        $this->onlinePos  = 'draft-' . $draft->id;
        $this->robots     = 'noindex';
        $this->formTitle  = 'Delete draft';
        $this->crumbs     = $this->crumbs($this->formTitle, [$this->c->Router->link('Drafts'), 'Drafts']);
        $this->posts      = [$draft];
        $this->postsTitle = 'Delete draft info';
        $this->form       = $this->formDelete($args, $draft);

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formDelete(array $args, Draft $draft): array
    {
        return [
            'action' => $this->c->Router->link(
                'DeleteDraft',
                [
                    'did' => $draft->id,
                ]
            ),
            'hidden' => [
                'token' => $this->c->Csrf->create(
                    'DeleteDraft',
                    [
                        'did' => $draft->id,
                    ]
                ),
            ],
            'sets'   => [
                'info' => [
                    'inform' => [
                        [
                            'message' => ['Topic %s', $draft->parent->name],
                        ],
                    ],
                ],
                'confirm' => [
                    'fields' => [
                        'confirm' => [
                            'type'    => 'checkbox',
                            'label'   => 'Confirm delete draft',
                            'checked' => false,
                        ],
                    ],
                ],
            ],
            'btns'   => [
                'delete'  => [
                    'type'  => 'submit',
                    'value' => __('Delete  draft'),
                ],
                'cancel'  => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'href'  => $this->c->Router->link('Drafts'),
                ],
            ],
        ];
    }
}
