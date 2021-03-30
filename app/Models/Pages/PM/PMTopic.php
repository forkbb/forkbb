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
        } elseif ('new' === $args['more2']) {
            $new = $this->model->firstNew;

            if ($new > 0) {
                $new = $this->pms->load(Cnst::PPOST, $new);
            }

            if ($new instanceof PPost) {
                return $this->c->Redirect->url($new->link);
            } else {
                return $this->c->Redirect->url($this->model->linkLast);
            }
        } elseif ('' === \trim($args['more2'], '1234567890')) {
            $this->model->page = (int) $args['more2'];
        } else {
            return $this->c->Message->message('Not Found', true, 404);
        }

        return $this->view($args, $method);
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

        $this->args       = $args;
        $this->targetUser = $this->model->ztUser;
        $this->pms->area  = $this->pms->inArea($this->model);
        $this->pmIndex    = $this->pms->area;
        $this->nameTpl    = 'pm/topic';
        $this->pmCrumbs[] = $this->model;

        if (
            $this->model->canReply
            && '1' == $this->c->config->o_quickpost
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
