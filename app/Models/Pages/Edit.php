<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\Poll\Model as Poll;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\Topic\Model as Topic;
use function \ForkBB\__;

class Edit extends Page
{
    use PostFormTrait;
    use PostValidatorTrait;

    /**
     * Редактирование сообщения
     */
    public function edit(array $args, string $method): Page
    {
        $post = $this->c->posts->load($args['id']);

        if (
            empty($post)
            || ! $post->canEdit
        ) {
            return $this->c->Message->message('Bad request');
        }

        $topic       = $post->parent;
        $editSubject = $post->id === $topic->first_post_id;

        $this->c->Lang->load('post');

        if ('1' == $this->c->config->b_poll_enabled) {
            $this->c->Lang->load('poll');
        }

        if ('POST' === $method) {
            $v = $this->messageValidator($post, 'EditPost', $args, true, $editSubject);

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

                if (
                    $editSubject
                    && $this->user->usePoll
                    && $v->poll_enable
                ) {
                    $this->poll = $this->c->polls->create($v->poll);
                    $this->c->polls->revision($this->poll, true);
                }
            }
        } else {
            $args['_vars'] = [
                'message'      => $post->message,
                'subject'      => $topic->subject,
                'hide_smilies' => $post->hide_smilies,
                'stick_topic'  => $topic->sticky,
                'stick_fp'     => $topic->stick_fp,
                'edit_post'    => $post->edit_post,
            ];
        }

        if (
            $editSubject
            && $this->user->usePoll
        ) {
            if (
                ($poll = $topic->poll) instanceof Poll
                && (
                    ! $poll->canEdit
                    || 'POST' !== $method
                )
            ) {
                $args['_vars'] = \array_merge($args['_vars'], [
                    'pollNoEdit'   => ! $poll->canEdit,
                    'poll_enable'  => $topic->poll_type > 0,
                    'poll'         => [
                        'duration'    => $topic->poll_type > 1000 ? $topic->poll_type - 1000 : 0, // ???? перенести в модель poll?
                        'hide_result' => $topic->poll_term > 0,
                        'question'    => $poll->question,
                        'type'        => $poll->type,
                        'answer'      => $poll->answer,
                    ],
                ]);
            }

            if (
                null !== $this->previewHtml
                && $args['_vars']['poll_enable']
            ) {
                $this->poll = $this->c->polls->create($args['_vars']['poll']);
                $this->c->polls->revision($this->poll, true);
            }
        }

        $this->nameTpl   = 'post';
        $this->onlinePos = 'topic-' . $topic->id;
        $this->canonical = $post->linkEdit;
        $this->robots    = 'noindex';
        $this->formTitle = $editSubject ? __('Edit topic') : __('Edit post');
        $this->crumbs    = $this->crumbs($this->formTitle, $topic);
        $this->form      = $this->messageForm($args, $post, 'EditPost', true, $editSubject);

        return $this;
    }

    /**
     * Сохранение сообщения
     */
    protected function endEdit(Post $post, Validator $v): Page
    {
        $now         = \time();
        $executive   = $this->user->isAdmin || $this->user->isModerator($post);
        $topic       = $post->parent;
        $editSubject = $post->id === $topic->first_post_id;
        $calcPost    = false;
        $calcTopic   = false;
        $calcForum   = false;

        // текст сообщения
        if ($post->message !== $v->message) {
            $post->message       = $v->message;
            $post->edited        = $now;
            $post->editor        = $this->user->username;
            $post->editor_id     = $this->user->id;
            $calcPost            = true;
            if ($post->id === $topic->last_post_id) {
                $calcTopic       = true;
                $calcForum       = true;
            }
        }
        // показ смайлов
        if (
            '1' == $this->c->config->o_smilies
            && (bool) $post->hide_smilies !== (bool) $v->hide_smilies
        ) {
            $post->hide_smilies  = $v->hide_smilies ? 1 : 0;
        }
        // редактирование без ограничений
        if (
            $executive
            && (bool) $post->edit_post !== (bool) $v->edit_post
        ) {
            $post->edit_post     = $v->edit_post ? 1 : 0;
        }

        if ($editSubject) {
            // заголовок темы
            if ($topic->subject !== $v->subject) {
                $topic->subject  = $v->subject;
                $post->edited    = $now;
                $post->editor    = $this->user->username;
                $post->editor_id = $this->user->id;
                $calcForum       = true;
            }
            // выделение темы
            if (
                $executive
                && (bool) $topic->sticky !== (bool) $v->stick_topic
            ) {
                $topic->sticky   = $v->stick_topic ? 1 : 0;
            }
            // закрепление первого сообшения
            if (
                $executive
                && (bool) $topic->stick_fp !== (bool) $v->stick_fp
            ) {
                $topic->stick_fp = $v->stick_fp ? 1 : 0;
            }
            // опрос
            if ($this->user->usePoll) {
                $this->changePoll($topic, $v);
            }
        }

        // обновление сообщения
        $this->c->posts->update($post);

        // обновление темы
        if ($calcTopic) {
            $topic->calcStat();
        }
        $this->c->topics->update($topic);

        // обновление раздела
        if ($calcForum) {
            $topic->parent->calcStat();
        }
        $this->c->forums->update($topic->parent);

        // антифлуд
        if (
            $calcPost
            || $calcForum
        ) {
            $this->user->last_post = $now; //????
            $this->c->users->update($this->user);
        }

        $this->c->search->index($post, 'edit');

        return $this->c->Redirect->page('ViewPost', ['id' => $post->id])->message('Edit redirect');
    }

    /**
     * Изменяет(удаляет/добавляет) данные опроса
     */
    protected function changePoll(Topic $topic, Validator $v): void
    {
        if ($topic->poll_type > 0 ) {
            $poll = $topic->poll;

            if (! $poll->canEdit) {
                return;
            }

            // редактирование
            if ($v->poll_enable) {
                $topic->poll_type  = $v->poll['duration'] > 0 ? 1000 + $v->poll['duration'] : 1; // ???? перенести в модель poll?
#                $topic->poll_time  = 0;
                $topic->poll_term  = $v->poll['hide_result']
                    ? ($topic->poll_term ?: $this->c->config->i_poll_term)
                    : 0;

                $poll->__question = $v->poll['question'];
                $poll->__answer   = $v->poll['answer'];
                $poll->__type     = $v->poll['type'];

                $this->c->polls->update($poll);
            // удаление
            } else {
                $topic->poll_type  = 0;
                $topic->poll_time  = 0;
                $topic->poll_term  = 0;

                $this->c->polls->delete($poll);
            }
        // добавление
        } elseif ($v->poll_enable) {
            $topic->poll_type  = $v->poll['duration'] > 0 ? 1000 + $v->poll['duration'] : 1; // ???? перенести в модель poll?
            $topic->poll_time  = \time();
            $topic->poll_term  = $v->poll['hide_result'] ? $this->c->config->i_poll_term : 0;

            $poll = $this->c->polls->create([
                'tid'      => $topic->id,
                'question' => $v->poll['question'],
                'answer'   => $v->poll['answer'],
                'type'     => $v->poll['type'],
            ]);

            $this->c->polls->insert($poll);
        }
    }
}
