<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages\PM;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Pages\PM\AbstractPM;
use ForkBB\Models\Pages\PostFormTrait;
use ForkBB\Models\Pages\PostValidatorTrait;
use ForkBB\Models\PM\Cnst;
use ForkBB\Models\PM\PPost;
use ForkBB\Models\PM\PTopic;
use InvalidArgumentException;
use function \ForkBB\__;

class PMEdit extends AbstractPM
{
    use PostFormTrait;
    use PostValidatorTrait;

    /**
     * Редактирование сообщения
     */
    public function edit(array $args, string $method): Page
    {
        if (isset($args['more2'])) {
            return $this->c->Message->message('Bad request');
        }

        $post = $this->pms->load(Cnst::PPOST, $args['more1']);

        if (
            ! $post instanceof PPost
            || ! $post->canEdit
        ) {
            return $this->c->Message->message('Bad request');
        }

        $topic     = $post->parent;
        $firstPost = $post->id === $topic->first_post_id;

        $this->c->Lang->load('post');

        if ('POST' === $method) {
            $v = $this->messageValidatorPM(null, 'PMAction', $args, true, $firstPost);

            if (
                $v->validation($_POST)
                && null === $v->preview
                && null !== $v->submit
            ) {
                return $this->endEdit($post, $v);
            }

            $this->fIswev  = $v->getErrors();
            $args['_vars'] = $v->getData();

            if (
                null !== $v->preview
                && ! $v->getErrors()
            ) {
                $this->previewHtml = $this->c->censorship->censor(
                    $this->c->Parser->parseMessage(null, (bool) $v->hide_smilies)
                );
            }
        } else {
            $args['_vars'] = [
                'message'      => $post->message,
                'subject'      => $topic->subject,
                'hide_smilies' => $post->hide_smilies,
            ];
        }

        $this->targetUser = $topic->ztUser;
        $this->pms->area  = $this->pms->inArea($topic);
        $this->pmIndex    = $this->pms->area;
        $this->nameTpl    = 'pm/post';
        $this->formTitle  = $firstPost ? 'Edit PT title' : 'Edit PM title';
        $this->form       = $this->messageFormPM(null, 'PMAction', $args, true, $firstPost, false);
        $this->pmCrumbs[] = [
            $this->c->Router->link('PMAction', $args),
            $firstPost ? 'Edit dialogue' : 'Edit message',
        ];
        $this->pmCrumbs[] = $topic;

        return $this;
    }

    protected function messageFormPM(?Model $model, string $marker, array $args, bool $edit, bool $first, bool $quick): array
    {
        $form = $this->messageForm($model, $marker, $args, $edit, $first, $quick);

        if (Cnst::ACTION_ARCHIVE === $this->pms->area) {
            $form['btns']['submit']['value'] = __('Save');
        }

        return $form;
    }

    protected function messageValidatorPM(?Model $model, string $marker, array $args, bool $edit, bool $first): Validator
    {
        $v = $this->messageValidator($model, $marker, $args, $edit, $first)
            ->addRules([
                'message' => 'required|string:trim|max:65535 bytes|check_message',
            ])->addArguments([
                'submit.check_timeout' => $this->user->u_pm_last_post,
            ]);

        return $v;
    }

    /**
     * Сохранение сообщения
     */
    protected function endEdit(PPost $post, Validator $v): Page
    {
        $now       = \time();
        $topic     = $post->parent;
        $firstPost = $post->id === $topic->first_post_id;
        $calcUser  = false;
        $calcTopic = false;

        // текст сообщения
        if ($post->message !== $v->message) {
            $post->message       = $v->message;
            $post->edited        = $now;
            $calcUser            = true;

            if ($post->id === $topic->last_post_id) {
                $calcTopic       = true;
            }
        }
        // показ смайлов
        if (
            1 === $this->c->config->b_smilies
            && (bool) $post->hide_smilies !== (bool) $v->hide_smilies
        ) {
            $post->hide_smilies  = $v->hide_smilies ? 1 : 0;
        }

        if ($firstPost) {
            // заголовок темы
            if ($topic->subject !== $v->subject) {
                $topic->subject  = $v->subject;
                $post->edited    = $now;
            }
        }

        $this->pms->update(Cnst::PPOST, $post);

        // пересчет темы
        if ($calcTopic) {
            $topic->calcStat();
        }

        $this->pms->update(Cnst::PTOPIC, $topic);

        // антифлуд
        if ($calcUser) {
            $this->user->u_pm_last_post = $now;

            $this->c->users->update($this->user);
        }

        return $this->c->Redirect->url($post->link)->message('Edit redirect', FORK_MESS_SUCC);
    }
}
