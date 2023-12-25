<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\PM;

use ForkBB\Models\Page;
use ForkBB\Models\Pages\PM\AbstractPM;
use ForkBB\Models\Pages\PostFormTrait;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use function \ForkBB\__;

class PMTopic extends AbstractPM
{
    use PostFormTrait;

    /**
     * Отображает приватный диалог по номеру поста
     */
    public function post(array $args, string $method): Page
    {
        if (
            ! isset($args['more1'])
            || isset($args['more2'])
        ) {
            return $this->c->Message->message('Bad request');
        }

        $post = $this->pms->load(Cnst::PPOST, $args['more1']);

        if (! $post instanceof PPost) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $this->model = $post->parent;

        $this->model->calcPage($post->id);

        return $this->view($args, $method);
    }

    /**
     * Отображает приватный диалог по его номеру
     */
    public function topic(array $args, string $method): Page
    {
        if (! isset($args['more1'])) {
            return $this->c->Message->message('Bad request');
        }

        $this->model = $this->pms->load(Cnst::PTOPIC, $args['more1']);

        if (! $this->model instanceof PTopic) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        if (! isset($args['more2'])) {
            $this->model->page = 1;
        } elseif (Cnst::ACTION_NEW === $args['more2']) {
            $new = $this->model->firstNew;

            if ($new > 0) {
                $new = $this->pms->load(Cnst::PPOST, $new);
            }

            return $this->c->Redirect->url($new instanceof PPost ? $new->link : $this->model->linkLast);
        } elseif (Cnst::ACTION_SEND === $args['more2']) {
            return $this->send($args, $method);
        } elseif ('' === \trim($args['more2'], '1234567890')) {
            $this->model->page = (int) $args['more2'];
        } else {
            return $this->c->Message->message('Not Found', true, 404);
        }

        return $this->view($args, $method);
    }

    /**
     * Подготовка формы и отправка диалога
     */
    protected function send(array $args, string $method): Page
    {
        if (! $this->model->canSend) {
            return $this->c->Message->message('Bad request');
        }

        $this->args       = $args;
        $this->targetUser = $this->model->ztUser;

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'   => 'token:PMAction',
                    'confirm' => 'checkbox',
                    'send'    => 'required|string',
                ])->addAliases([
                ])->addArguments([
                    'token' => $args,
                ]);

            if (
                ! $v->validation($_POST)
                || '1' !== $v->confirm
            ) {
                return $this->c->Redirect->url($this->model->link)->message('No confirm redirect', FORK_MESS_WARN);
            }

            $this->model->poster_status = Cnst::PT_NORMAL; //????
            $this->model->target_status = Cnst::PT_NORMAL; //????

            $this->targetUser->u_pm_flash = 1;

            $this->pms->update(Cnst::PTOPIC, $this->model);
            $this->pms->recalculate($this->targetUser);
            $this->pms->recalculate($this->user);

            return $this->c->Redirect->url($this->model->link)->message('Send dialogue redirect', FORK_MESS_SUCC);
        }

        $this->identifier = ['pm', 'pm-send'];
        $this->pms->area  = $this->pms->inArea($this->model);
        $this->pmIndex    = $this->pms->area;
        $this->nameTpl    = 'pm/post';
        $this->formTitle  = 'Send PT title';
        $this->form       = $this->formSend($args);
        $this->postsTitle = 'Send info';
        $this->posts      = [$this->pms->load(Cnst::PPOST, $this->model->first_post_id)];
        $this->pmCrumbs[] = [$this->c->Router->link('PMAction', $args), 'Send  dialogue'];
        $this->pmCrumbs[] = $this->model;

        return $this;
    }

    /**
     * Подготавливает массив данных для формы
     */
    protected function formSend(array $args): array
    {
        return [
            'action' => $this->c->Router->link('PMAction', $args),
            'hidden' => [
                'token' => $this->c->Csrf->create('PMAction', $args),
            ],
            'sets'   => [
                'info' => [
                    'inform' => [
                        [
                            'message' => ['Dialogue %s', $this->model->name],
                        ],
                        [
                            'message' => ['Recipient: %s', $this->targetUser->username],
                        ],
                    ],
                ],
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
                'send'  => [
                    'type'  => 'submit',
                    'value' => __('Send dialogue'),
                ],
                'cancel'  => [
                    'type'  => 'btn',
                    'value' => __('Cancel'),
                    'href'  => $this->model->link,
                ],
            ],
        ];
    }

    /**
     * Подготовка данных для шаблона
     */
    protected function view(array $args, string $method): Page
    {
        if (! $this->model->hasPage()) {
            return $this->c->Message->message('Not Found', true, 404);
        }

        $this->posts = $this->model->pageData();

        if (
            empty($this->posts)
            && $this->model->page > 1
        ) {
            return $this->c->Redirect->url($this->model->link);
        }

        $this->c->Lang->load('topic');

        $this->identifier = ['pm', 'pm-topic'];
        $this->args       = $args;
        $this->targetUser = $this->model->ztUser;
        $this->pms->area  = $this->pms->inArea($this->model);
        $this->pmIndex    = $this->pms->area;
        $this->nameTpl    = 'pm/topic';
        $this->pmCrumbs[] = $this->model;

        if (
            $this->model->canReply
            && 1 === $this->c->config->b_quickpost
        ) {
            $form = $this->messageForm(null, 'PMAction', $this->model->dataReply, false, false, true);

            if (Cnst::ACTION_ARCHIVE === $this->pmIndex) {
                $form['btns']['submit']['value'] = __('Save');
            }

            $this->form = $form;
        }

        $this->model->updateVisit();

        return $this;
    }
}
